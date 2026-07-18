<?php

declare(strict_types=1);

namespace STS\Docent\Admin;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Models\DocentPageRevision;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Renderer\TableOfContents;
use STS\Docent\Documents\Renderer\TocEntry;
use STS\Docent\Documents\Serializer\AstToTiptap;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\Icon;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\DocsChecker;
use STS\Docent\Validation\Issue;
use Symfony\Component\Yaml\Yaml;

/**
 * The admin panel's authoring backend — tree, editor payloads, drafts,
 * previews, export, and group metadata for one site.
 */
final class Editor
{
    /** Where the effective page/group content comes from — the admin JS compares these exact strings. */
    private const STORE_DATABASE = 'database';

    private const STORE_FILESYSTEM = 'filesystem';

    private const GROUP_SOURCE_DATABASE = 'database';

    private const GROUP_SOURCE_FILE = 'file';

    public function __construct(
        private readonly DocentManager $docent,
        private readonly DocumentationRepository $repository,
        private readonly FilesystemRepository $filesystem,
        private readonly DocumentParser $parser,
        private readonly IntegrationRegistry $registry,
    ) {}

    /**
     * The admin page tree: every database page (drafts included — the whole
     * point of the panel) and every filesystem page, as a flat list the UI can
     * group by directory. `shadowed` marks the drift the ROADMAP insists we
     * surface: a database page overriding a like-named file (true on both the
     * database entry doing the shadowing and the file entry being shadowed).
     * Underscored slugs (`_partials/…`) are excluded, mirroring the reader
     * enumeration. Filesystem pages are read-only here; their `published` /
     * `hasUnpublishedChanges` are null.
     *
     * @return list<array{
     *     slug: string, title: string, group: string, store: string,
     *     shadowed: bool, published: bool|null, hasUnpublishedChanges: bool|null, hidden: bool, locked: bool
     * }>
     */
    public function adminTree(): array
    {
        $files = [];

        foreach ($this->filesystem->all() as $reference) {
            $files[$reference->slug] = $reference;
        }

        $pages = DocentPage::forSite($this->databaseConnection(), $this->docent->key())->get();

        $dbSlugs = [];

        foreach ($pages as $page) {
            $dbSlugs[$page->slug] = true;
        }

        $entries = [];

        foreach ($pages as $page) {
            if ($this->isUnderscored($page->slug)) {
                continue;
            }

            $frontMatter = new FrontMatter($page->front_matter ?? []);
            $redirectStub = $frontMatter->hasRedirect();

            $entries[] = [
                'slug' => $page->slug,
                'title' => $page->title,
                'group' => $this->baseDirOf($page->slug),
                'store' => self::STORE_DATABASE,
                'shadowed' => isset($files[$page->slug]),
                'published' => $page->isPublished(),
                'hasUnpublishedChanges' => $page->hasUnpublishedChanges(),
                'hidden' => $frontMatter->hidden() || $redirectStub,
                'locked' => isset($files[$page->slug]) && $files[$page->slug]->locked,
            ];
        }

        foreach ($files as $slug => $reference) {
            $entries[] = [
                'slug' => $slug,
                'title' => $reference->title,
                'group' => $reference->directory,
                'store' => self::STORE_FILESYSTEM,
                'shadowed' => isset($dbSlugs[$slug]),
                'published' => null,
                'hasUnpublishedChanges' => null,
                'hidden' => $reference->hidden,
                'locked' => $reference->locked || $reference->redirectStub,
            ];
        }

        return $entries;
    }

    /**
     * The editor payload for a slug: the database draft (its current, possibly
     * unpublished content) when one exists, otherwise the read-only filesystem
     * page. Null when neither store has the slug (404).
     *
     * @return array<string, mixed>|null
     */
    public function adminDetail(string $slug): ?array
    {
        if ($this->filesystem->pageLocked($slug)) {
            $source = $this->filesystem->find($slug);

            return $source === null ? null : $this->filesystemDetail($slug, $source);
        }

        $page = DocentPage::forSite($this->databaseConnection(), $this->docent->key())->where('slug', $slug)->first();

        if ($page !== null) {
            return $this->databaseDetail($page);
        }

        $source = $this->filesystem->find($slug);

        return $source === null ? null : $this->filesystemDetail($slug, $source);
    }

    public function filesystemSlugLocked(string $slug): bool
    {
        if (str_starts_with($slug, '_partials/')) {
            return $this->filesystem->partialLocked(substr($slug, strlen('_partials/')));
        }

        if ($this->filesystem->pageLocked($slug)) {
            return true;
        }

        $source = $this->filesystem->find($slug);

        if ($source === null) {
            return false;
        }

        [$frontMatter] = $this->splitFrontMatter($source->rawContent);

        return (new FrontMatter($frontMatter))->hasRedirect();
    }

    /**
     * Every directory that currently holds pages — across the filesystem and the
     * database, and including each nested ancestor as its own entry — with its
     * effective group metadata. `source` records where the effective values come
     * from: 'database' (a `_groups/` override row exists), 'file' (a `_group.yml`
     * provides it), or null (pure defaults). `''` (root) is never a group.
     * Effective label/order/icon reflect the composite cascade (database wins).
     *
     * @return list<array{directory: string, label: string, order: int|null, icon: string|null, source: string|null}>
     */
    public function adminGroups(): array
    {
        $pages = DocentPage::forSite($this->databaseConnection(), $this->docent->key())->get();

        $directories = [];
        $dbGroups = [];

        foreach ($pages as $page) {
            if (str_starts_with($page->slug, '_groups/')) {
                $dbGroups[substr($page->slug, strlen('_groups/'))] = true;

                continue;
            }

            if ($this->isUnderscored($page->slug)) {
                continue;
            }

            $this->collectDirectories($directories, $this->baseDirOf($page->slug));
        }

        foreach ($this->filesystem->all() as $reference) {
            $this->collectDirectories($directories, $reference->directory);
        }

        $groups = [];

        foreach (array_keys($directories) as $directory) {
            $meta = $this->repository->groupMeta($directory) ?? [];
            $order = $meta['order'] ?? null;
            $label = $meta['label'] ?? null;
            $icon = $meta['icon'] ?? null;

            $groups[] = [
                'directory' => $directory,
                'label' => is_scalar($label) && (string) $label !== '' ? (string) $label : Str::headline(Str::afterLast($directory, '/')),
                'order' => is_numeric($order) ? (int) $order : null,
                'icon' => is_string($icon) && $icon !== '' ? $icon : null,
                'source' => isset($dbGroups[$directory])
                    ? self::GROUP_SOURCE_DATABASE
                    : ($this->filesystem->groupMeta($directory) !== null ? self::GROUP_SOURCE_FILE : null),
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$a['order'] ?? PHP_INT_MAX, $a['label']] <=> [$b['order'] ?? PHP_INT_MAX, $b['label']]);

        return $groups;
    }

    /**
     * Store (or replace) a directory's group metadata as a reserved, never-published
     * `_groups/{directory}` row — taking effect immediately, no publish step. The
     * database override wins over any `_group.yml` of the same directory.
     *
     * @param  array<string, mixed>  $meta
     */
    public function updateGroupMeta(string $directory, array $meta, ?int $authorId): void
    {
        DocentPage::write(
            '_groups/'.$directory,
            '',
            $meta,
            $authorId,
            site: $this->docent->key(),
            connection: $this->databaseConnection(),
        );
    }

    /**
     * Discard a directory's database group override, restoring whatever the
     * filesystem `_group.yml` (or defaults) provide. False when none existed.
     */
    public function removeGroupMeta(string $directory): bool
    {
        return (bool) DocentPage::forSite($this->databaseConnection(), $this->docent->key())
            ->where('slug', '_groups/'.$directory)
            ->first()?->delete();
    }

    /**
     * Copy a filesystem page into a new database draft, so it can be edited
     * without touching the repository. Returns null when no such file exists;
     * the caller rejects an already-overridden slug (409) before calling.
     */
    public function overrideFromFilesystem(string $slug, ?int $authorId): ?DocentPage
    {
        $source = $this->filesystem->find($slug);

        if ($source === null) {
            return null;
        }

        [$frontMatter, $content] = $this->splitFrontMatter($source->rawContent);

        if ((new FrontMatter($frontMatter))->hasRedirect()) {
            return null;
        }

        return DocentPage::write(
            $slug,
            $content,
            $frontMatter,
            $authorId,
            site: $this->docent->key(),
            connection: $this->databaseConnection(),
        );
    }

    /**
     * Parse an admin draft into a Document ready for preview, checks, or export.
     * A markdown draft is its front matter composed back over the body and
     * parsed as usual; a Tiptap draft is JSON parsed by {@see TiptapDocumentParser}
     * with the form's front matter applied over the (metadata-free) body — the
     * same override the database repository performs for published Tiptap pages.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    public function draftDocument(string $format, string $content, array $frontMatter): Document
    {
        return $format === DocumentSource::FORMAT_TIPTAP
            ? (new TiptapDocumentParser)->parse($content)->withFrontMatter($frontMatter)
            : $this->parser->parse($this->composeMarkdown($frontMatter, $content));
    }

    /**
     * Validate that a Tiptap document (as decoded from the editor) parses into
     * the AST, returning the parser's message on failure or null on success.
     * The store/preview controllers turn a non-null result into a 422 — this is
     * the one place we validate an untrusted editor payload at the boundary.
     *
     * @param  array<string, mixed>  $content
     */
    public function tiptapError(array $content): ?string
    {
        try {
            (new TiptapDocumentParser)->parse(json_encode($content, JSON_THROW_ON_ERROR));

            return null;
        } catch (JsonException|InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Export any page — file or database, markdown or Tiptap — to normalized
     * markdown with a front matter block, the pivot always being the Docent AST.
     * Powers the admin "View markdown" / file-export action. Null when no such
     * page exists.
     */
    public function exportMarkdown(string $slug): ?string
    {
        $page = $this->filesystemSlugLocked($slug)
            ? null
            : DocentPage::forSite($this->databaseConnection(), $this->docent->key())->where('slug', $slug)->first();

        if ($page !== null) {
            $frontMatter = $page->front_matter ?? ['title' => $page->title];
            $document = $this->draftDocument($page->format, $page->content, $frontMatter);

            return (new MarkdownExporter)->withFrontMatter($frontMatter)->export($document);
        }

        $source = $this->filesystem->find($slug);

        if ($source === null) {
            return null;
        }

        [$frontMatter, $content] = $this->splitFrontMatter($source->rawContent);
        $document = $this->draftDocument($source->format, $content, $frontMatter);

        return (new MarkdownExporter)->withFrontMatter($frontMatter)->export($document);
    }

    /**
     * Render a draft through the real pipeline (HtmlRenderer + TOC) with the
     * given viewer's context, plus the inline reference checks — no persistence.
     * This is exactly what a reader would see if the draft were published and
     * they were this viewer.
     *
     * @return array{html: string, toc: list<array<string, mixed>>, issues: list<array<string, mixed>>}
     */
    public function previewDraft(Document $document, DocumentationContext $context, string $slug = ''): array
    {
        $document = $document->withHtmlPolicy($this->docent->databaseHtmlPolicy());

        return [
            'html' => $this->docent->renderDocument($document, $context),
            'toc' => $this->tocToArray((new TableOfContents($this->registry, $context))->buildFor($document)),
            'issues' => $this->draftIssues($slug, $document),
        ];
    }

    /**
     * The reference-check issues for a pre-parsed draft, as plain arrays ready
     * for JSON — unknown integrations, broken internal links, and missing
     * includes for the document as it would resolve under the given slug. Format
     * agnostic: the draft is already an AST, so markdown and Tiptap drafts run
     * the identical checks.
     *
     * @return list<array{severity: string, check: string, message: string, line: int|null}>
     */
    public function draftIssues(string $slug, Document $document): array
    {
        $context = new CheckContext(
            repository: $this->repository,
            parser: $this->parser,
            registry: $this->registry,
            docsPath: (string) ($this->docent->config('filesystem.path') ?? resource_path('docs')),
            publicPath: public_path(),
            routePrefix: (string) $this->docent->config('route.prefix', 'docs'),
            routeExists: static fn (string $name): bool => Route::has($name),
            abilityExists: static fn (string $ability): bool => Gate::has($ability),
            overrideSlug: $slug,
            overrideDocument: $document,
            docent: $this->docent,
        );

        return array_map(
            static fn (Issue $issue): array => [
                'severity' => $issue->severity->value,
                'check' => $issue->check,
                'message' => $issue->message,
                'line' => $issue->line,
            ],
            DocsChecker::references()->run($context),
        );
    }

    /**
     * Registry metadata for the editor's node/reference pickers: conditions,
     * values, links, components, and audiences (name/label/description), plus
     * the built-in icon names and every registered Gate ability. Abilities
     * carry a humanized `label` alongside the technical `name` so every picker
     * shows "View reports", not `reports.view` — the stored value stays the
     * technical name.
     *
     * @return array<string, mixed>
     */
    public function pickerMeta(): array
    {
        return [
            ...$this->registry->describe(),
            'icons' => Icon::names(),
            'abilities' => array_map(
                fn (string $ability): array => ['name' => $ability, 'label' => $this->abilityLabel($ability)],
                array_keys(Gate::abilities()),
            ),
        ];
    }

    /**
     * A human label for a technical gate ability: split on separators and
     * camelCase, and lead with the verb when the name ends in one —
     * `reports.view` → "View reports", `manage-billing` → "Manage billing".
     */
    private function abilityLabel(string $ability): string
    {
        $words = array_map(
            strtolower(...),
            preg_split('/[.\-_:\s]+|(?=[A-Z])/', $ability, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        $verbs = ['view', 'see', 'read', 'access', 'manage', 'edit', 'update', 'create', 'delete', 'export', 'download'];

        if (count($words) > 1 && in_array(end($words), $verbs, true)) {
            array_unshift($words, array_pop($words));
        }

        return Str::ucfirst(implode(' ', $words));
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseDetail(DocentPage $page): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'content_tiptap' => $this->tiptapFor($page->content, $page->format),
            'front_matter' => $page->front_matter ?? [],
            'format' => $page->format,
            'published' => $page->isPublished(),
            'hasUnpublishedChanges' => $page->hasUnpublishedChanges(),
            'revisions' => $page->revisions()->latest('id')->limit(20)->get()->map(
                static fn (DocentPageRevision $revision): array => [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at,
                    'created_by' => $revision->created_by,
                ],
            )->all(),
            'store' => self::STORE_DATABASE,
            'locked' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filesystemDetail(string $slug, DocumentSource $source): array
    {
        [$frontMatter, $content] = $this->splitFrontMatter($source->rawContent);
        $redirectStub = (new FrontMatter($frontMatter))->hasRedirect();

        return [
            'slug' => $slug,
            'title' => (new FrontMatter($frontMatter))->title() ?? ($slug === '' ? 'Home' : Str::headline(Str::afterLast($slug, '/'))),
            'content' => $content,
            'content_tiptap' => $this->tiptapFor($content, $source->format),
            'front_matter' => $frontMatter,
            'format' => $source->format,
            'store' => self::STORE_FILESYSTEM,
            'readonly' => true,
            'locked' => $this->filesystemSlugLocked($slug) || $redirectStub,
        ];
    }

    /**
     * The editor's Tiptap view of a page's body. A Tiptap page is decoded
     * straight from its stored JSON; a markdown page is parsed and serialized so
     * the visual editor can open any page. (Stored JSON is our own data, so a
     * decode failure is a bug and throws.)
     *
     * @return array<string, mixed>
     */
    private function tiptapFor(string $content, string $format): array
    {
        if ($format === DocumentSource::FORMAT_TIPTAP) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        }

        return (new AstToTiptap)->convert($this->parser->parse($content));
    }

    /**
     * Split a raw markdown file into its parsed front matter and its body. Repo
     * files are app code (reviewed in PRs), so a malformed YAML block is a bug
     * that should surface loudly rather than be swallowed.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $raw): array
    {
        if (preg_match('/^---\R(.*?)\R---\s*(?:\R|$)/s', $raw, $matches) !== 1) {
            return [[], $raw];
        }

        $data = Yaml::parse($matches[1]);

        return [is_array($data) ? $data : [], substr($raw, strlen($matches[0]))];
    }

    /**
     * Compose a page's front matter (when any) and body back into a single
     * markdown document, the shape the parser and reference checks expect.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    private function composeMarkdown(array $frontMatter, string $content): string
    {
        if ($frontMatter === []) {
            return $content;
        }

        return '---'."\n".Yaml::dump($frontMatter).'---'."\n\n".$content;
    }

    /**
     * @param  list<TocEntry>  $entries
     * @return list<array{title: string, slug: string, level: int, children: array<int, mixed>}>
     */
    private function tocToArray(array $entries): array
    {
        return array_map(
            fn (TocEntry $entry): array => [
                'title' => $entry->title,
                'slug' => $entry->slug,
                'level' => $entry->level,
                'children' => $this->tocToArray($entry->children),
            ],
            $entries,
        );
    }

    private function baseDirOf(string $slug): string
    {
        return str_contains($slug, '/') ? substr($slug, 0, (int) strrpos($slug, '/')) : '';
    }

    /**
     * Record a directory and every ancestor prefix as its own group entry —
     * `guides/advanced` contributes both `guides` and `guides/advanced`, mirroring
     * the group node NavigationBuilder creates for each path segment.
     *
     * @param  array<string, true>  $set
     */
    private function collectDirectories(array &$set, string $directory): void
    {
        if ($directory === '') {
            return;
        }

        $accumulated = '';

        foreach (explode('/', $directory) as $segment) {
            $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;
            $set[$accumulated] = true;
        }
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

    private function databaseConnection(): ?string
    {
        $connection = $this->docent->config('database.connection');

        return $connection === null ? null : (string) $connection;
    }
}
