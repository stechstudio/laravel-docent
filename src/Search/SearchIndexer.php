<?php

declare(strict_types=1);

namespace STS\Docent\Search;

use Illuminate\Support\Str;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Renderer\NodeText;
use STS\Docent\Documents\Renderer\SearchTextRenderer;
use STS\Docent\Support\DocentCache;

/** Builds and caches the context-free, leak-safe ranked-search index. */
final class SearchIndexer
{
    private const SCHEMA_VERSION = 2;

    public function __construct(
        private readonly DocumentationRepository $repository,
        private readonly DocentCache $cache,
        private readonly DocentManager $manager,
    ) {}

    public function index(): SearchIndex
    {
        $key = implode(':', ['search', 'v'.self::SCHEMA_VERSION, $this->repository->directoryHash()]);

        return $this->cache->remember($key, fn (): SearchIndex => $this->build());
    }

    /** @return list<SearchRecord> */
    public function records(): array
    {
        return $this->index()->records;
    }

    private function build(): SearchIndex
    {
        $records = [];
        $order = 0;

        foreach ($this->repository->all() as $reference) {
            if ($reference->hidden || $reference->searchExcluded) {
                continue;
            }

            $page = $this->manager->page($reference->slug);

            if ($page === null) {
                continue;
            }

            $document = $page->document();
            $renderer = $this->renderer();
            $headings = $this->headings($document);
            $sections = $this->sections($document, $headings);

            $records[] = new SearchRecord(
                slug: $reference->slug,
                title: $reference->title,
                description: $reference->description,
                headings: $headings,
                body: $renderer->render($document),
                group: $this->group($reference->directory),
                authorize: $reference->authorize,
                audience: $reference->audience,
                keywords: $reference->searchKeywords,
                titleTokens: SearchTokenizer::tokenize($reference->title),
                descriptionTokens: SearchTokenizer::tokenize((string) $reference->description),
                keywordTokens: SearchTokenizer::tokenize(implode(' ', $reference->searchKeywords)),
                sections: $sections,
                order: $order++,
            );
        }

        return new SearchIndex(
            records: $records,
            documentFrequencies: $this->documentFrequencies($records),
            averageFieldLengths: $this->averageFieldLengths($records),
        );
    }

    private function renderer(): SearchTextRenderer
    {
        return new SearchTextRenderer(fn (string $name): ?Document => $this->manager->partialDocument($name));
    }

    /**
     * Build non-overlapping top-level sections. Standard Markdown headings are
     * direct document children; any nested heading still receives an empty
     * fallback section so its anchor remains searchable without duplicating a
     * container's entire body in every record.
     *
     * @param  list<array{title: string, slug: string}>  $headings
     * @return list<SearchSection>
     */
    private function sections(Document $document, array $headings): array
    {
        $renderer = $this->renderer();
        $sections = [];
        $title = null;
        $slug = null;
        $chunks = [];
        $order = 0;

        $flush = function () use (&$sections, &$title, &$slug, &$chunks, &$order): void {
            $body = trim(implode("\n\n", array_filter($chunks, static fn (string $chunk): bool => $chunk !== '')));
            if ($body !== '' || $title !== null || $sections === []) {
                $sections[] = new SearchSection(
                    title: $title,
                    slug: $slug,
                    body: $body,
                    headingTokens: SearchTokenizer::tokenize((string) $title),
                    bodyTokens: SearchTokenizer::tokenize($body),
                    order: $order++,
                );
            }
            $chunks = [];
        };

        foreach ($document->children as $child) {
            if ($child instanceof AuthorizationBlock || $child instanceof ConditionBlock || $child instanceof AudienceBlock) {
                continue;
            }

            if ($child instanceof Heading && $child->level >= 2) {
                $flush();
                $title = trim(NodeText::extract($child));
                $slug = $child->slug;

                continue;
            }

            $chunks[] = $renderer->render($child);
        }

        $flush();

        $known = array_filter(array_map(static fn (SearchSection $section): ?string => $section->slug, $sections));
        foreach ($headings as $heading) {
            if (in_array($heading['slug'], $known, true)) {
                continue;
            }

            $sections[] = new SearchSection(
                title: $heading['title'],
                slug: $heading['slug'],
                body: '',
                headingTokens: SearchTokenizer::tokenize($heading['title']),
                bodyTokens: [],
                order: $order++,
            );
        }

        return $sections;
    }

    /** @param list<SearchRecord> $records @return array<string, int> */
    private function documentFrequencies(array $records): array
    {
        $frequencies = [];

        foreach ($records as $record) {
            $tokens = [...$record->titleTokens, ...$record->descriptionTokens, ...$record->keywordTokens];
            foreach ($record->sections as $section) {
                array_push($tokens, ...$section->headingTokens, ...$section->bodyTokens);
            }

            foreach (array_unique(array_map(SearchTokenizer::stem(...), $tokens)) as $stem) {
                $frequencies[$stem] = ($frequencies[$stem] ?? 0) + 1;
            }
        }

        return $frequencies;
    }

    /** @param list<SearchRecord> $records @return array<string, float> */
    private function averageFieldLengths(array $records): array
    {
        $totals = ['title' => 0, 'description' => 0, 'keywords' => 0, 'heading' => 0, 'body' => 0];
        $counts = ['title' => 0, 'description' => 0, 'keywords' => 0, 'heading' => 0, 'body' => 0];

        foreach ($records as $record) {
            foreach (['title' => $record->titleTokens, 'description' => $record->descriptionTokens, 'keywords' => $record->keywordTokens] as $field => $tokens) {
                $totals[$field] += count($tokens);
                $counts[$field]++;
            }
            foreach ($record->sections as $section) {
                $totals['heading'] += count($section->headingTokens);
                $counts['heading']++;
                $totals['body'] += count($section->bodyTokens);
                $counts['body']++;
            }
        }

        $averages = array_map(
            static fn (int $total, string $field): float => max(1.0, $total / max(1, $counts[$field])),
            $totals,
            array_keys($totals),
        );

        return array_combine(array_keys($totals), $averages);
    }

    /** @return list<array{title: string, slug: string}> */
    private function headings(Document $document): array
    {
        $headings = [];
        $this->collectHeadings($document, $headings);

        return $headings;
    }

    /** @param list<array{title: string, slug: string}> $headings */
    private function collectHeadings(Node $node, array &$headings): void
    {
        foreach ($node->children as $child) {
            if ($child instanceof AuthorizationBlock || $child instanceof ConditionBlock || $child instanceof AudienceBlock) {
                continue;
            }
            if ($child instanceof Heading && $child->level >= 2) {
                $headings[] = ['title' => trim(NodeText::extract($child)), 'slug' => $child->slug];
            }
            $this->collectHeadings($child, $headings);
        }
    }

    private function group(string $directory): string
    {
        if ($directory === '') {
            return '';
        }

        $meta = $this->repository->groupMeta($directory);

        return isset($meta['label']) && is_string($meta['label'])
            ? $meta['label']
            : Str::headline(basename($directory));
    }
}
