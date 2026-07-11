<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

use Illuminate\Support\Str;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\PageReference;
use STS\Docent\Documents\FrontMatter;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads documentation from the `docent_pages` table, serving **published
 * revisions only** — the reader pipeline never sees drafts. A page's metadata
 * (title, front matter) is read from its published revision too, so navigation
 * and search reflect exactly what a reader can open.
 *
 * baseDir convention: database pages have no `index.md` notion, so a page's
 * base directory (for relative-link resolution) is simply the slug's parent
 * path — `''` at the root. Section-landing base-dir semantics (a section index
 * resolving links against its own directory) are revisited in Phase B.
 */
final class DatabaseRepository implements DocumentationRepository
{
    public function __construct(
        private readonly ?string $connection = null,
    ) {}

    public function find(string $slug): ?DocumentSource
    {
        return $this->isUnderscored($slug) ? null : $this->sourceForSlug($slug);
    }

    public function all(): iterable
    {
        $pages = DocentPage::on($this->connection)
            ->published()
            ->with('publishedRevision')
            ->get();

        foreach ($pages as $page) {
            if ($this->isUnderscored($page->slug)) {
                continue;
            }

            $frontMatter = new FrontMatter($page->publishedRevision?->front_matter ?? []);

            yield new PageReference(
                slug: $page->slug,
                title: $frontMatter->title() ?? $this->deriveTitle($page->slug),
                order: $frontMatter->order(),
                hidden: $frontMatter->hidden(),
                authorize: $frontMatter->authorize(),
                audience: $frontMatter->audience(),
                searchExcluded: $frontMatter->searchExcluded(),
                description: $frontMatter->description(),
                directory: $this->baseDirOf($page->slug),
            );
        }
    }

    public function partial(string $name): ?DocumentSource
    {
        return $this->sourceForSlug('_partials/'.$name);
    }

    public function groupMeta(string $directory): ?array
    {
        // The composite cascades directory metadata to the filesystem in Phase A.
        return null;
    }

    /**
     * Hashes the whole (non-trashed) pages table so any write, publish,
     * unpublish, or delete invalidates the navigation/search/AST caches — the
     * published projection is conservatively rebuilt even for a pure draft edit.
     */
    public function directoryHash(): string
    {
        $stats = DocentPage::on($this->connection)
            ->selectRaw('COUNT(*) as page_count, MAX(updated_at) as latest, SUM(published_revision_id) as published_sum')
            ->first();

        return sha1(
            (int) ($stats->page_count ?? 0)
            .'|'.($stats->latest ?? '')
            .'|'.(int) ($stats->published_sum ?? 0)
        );
    }

    private function sourceForSlug(string $slug): ?DocumentSource
    {
        $page = DocentPage::on($this->connection)
            ->published()
            ->with('publishedRevision')
            ->where('slug', $slug)
            ->first();

        $revision = $page?->publishedRevision;

        if ($revision === null) {
            return null;
        }

        $raw = $this->composeMarkdown($page, $revision->front_matter ?? [], $revision->content);

        return new DocumentSource(
            slug: $slug,
            rawContent: $raw,
            hash: sha1($raw),
            path: 'database:docent_pages/'.$page->getKey(),
            lastModified: (int) $revision->created_at?->getTimestamp(),
            baseDir: $this->baseDirOf($slug),
            format: $revision->format,
        );
    }

    /**
     * Compose the file-equivalent markdown for a page: the stored front matter
     * (always including the page title) is emitted as a YAML block ahead of the
     * content, so the parsed document carries the same metadata a file page
     * would — page-level `authorize`/`audience` gating and the title flow
     * through the render pipeline identically for both stores. The hash covers
     * the composed document, so metadata changes invalidate caches too.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    private function composeMarkdown(DocentPage $page, array $frontMatter, string $content): string
    {
        $frontMatter = ['title' => $page->title, ...$frontMatter];

        return '---'."\n".Yaml::dump($frontMatter).'---'."\n\n".$content;
    }

    private function baseDirOf(string $slug): string
    {
        return str_contains($slug, '/') ? substr($slug, 0, (int) strrpos($slug, '/')) : '';
    }

    private function deriveTitle(string $slug): string
    {
        return $slug === '' ? 'Home' : Str::headline(Str::afterLast($slug, '/'));
    }

    private function isUnderscored(string $slug): bool
    {
        foreach (explode('/', $slug) as $segment) {
            if (str_starts_with($segment, '_')) {
                return true;
            }
        }

        return false;
    }
}
