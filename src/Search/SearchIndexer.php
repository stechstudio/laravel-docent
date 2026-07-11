<?php

declare(strict_types=1);

namespace STS\Docent\Search;

use Illuminate\Support\Str;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Renderer\NodeText;
use STS\Docent\Documents\Renderer\SearchTextRenderer;
use STS\Docent\Support\DocentCache;

/**
 * Builds the search index — one {@see SearchRecord} per page — reusing the
 * manager's cached-parse path so pages are never re-parsed here. The result is
 * cached by the repository's `directoryHash()`, so it rebuilds automatically
 * whenever any content file changes, and `docent:clear` orphans it.
 *
 * The index is context-free by construction: bodies come from the leak-safe
 * {@see SearchTextRenderer}, and headings are read only from the document's top
 * level (never from inside conditional blocks), so nothing gated is indexed.
 */
final class SearchIndexer
{
    public function __construct(
        private readonly DocumentationRepository $repository,
        private readonly DocentCache $cache,
        private readonly DocentManager $manager,
    ) {}

    /**
     * @return list<SearchRecord>
     */
    public function records(): array
    {
        return $this->cache->remember('search:'.$this->repository->directoryHash(), fn (): array => $this->build());
    }

    /**
     * @return list<SearchRecord>
     */
    private function build(): array
    {
        $records = [];

        foreach ($this->repository->all() as $reference) {
            if ($reference->hidden || $reference->searchExcluded) {
                continue;
            }

            $page = $this->manager->page($reference->slug);

            if ($page === null) {
                continue;
            }

            $document = $page->document();

            $records[] = new SearchRecord(
                slug: $reference->slug,
                title: $reference->title,
                description: $reference->description,
                headings: $this->headings($document),
                body: $this->body($document),
                group: $this->group($reference->directory),
                authorize: $reference->authorize,
                audience: $reference->audience,
            );
        }

        return $records;
    }

    private function body(Document $document): string
    {
        $renderer = new SearchTextRenderer(fn (string $name): ?Document => $this->manager->partialDocument($name));

        return $renderer->render($document);
    }

    /**
     * Section headings (level 2+) with their anchor slugs, read only from the
     * document's top level so headings inside conditional blocks never leak.
     *
     * @return list<array{title: string, slug: string}>
     */
    private function headings(Document $document): array
    {
        $headings = [];

        foreach ($document->children as $child) {
            if ($child instanceof Heading && $child->level >= 2) {
                $headings[] = ['title' => trim(NodeText::extract($child)), 'slug' => $child->slug];
            }
        }

        return $headings;
    }

    private function group(string $directory): string
    {
        if ($directory === '') {
            return '';
        }

        $meta = $this->repository->groupMeta($directory);

        if (isset($meta['label']) && is_string($meta['label'])) {
            return $meta['label'];
        }

        return Str::headline(basename($directory));
    }
}
