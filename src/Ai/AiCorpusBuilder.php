<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\DocumentationContext;

/** Builds a bounded prompt corpus from question-dependent retrieval results. */
final class AiCorpusBuilder
{
    private const SCHEMA_VERSION = 2;

    public function __construct(
        private readonly DocentManager $docent,
        private readonly DocumentationRepository $repository,
        private readonly AiRetriever $retriever,
    ) {}

    public function version(DocumentationContext $context, bool $widget = false): string
    {
        return sha1(implode('|', [
            'v'.self::SCHEMA_VERSION,
            $this->repository->directoryHash(),
            $this->docent->viewerFingerprint($context),
            $widget ? 'widget' : 'reader',
            (string) max(1, (int) config('docent.ai.corpus_budget', 150000)),
            (string) max(1, (int) config('docent.ai.retrieval.max_pages', 8)),
            (string) max(1, (int) config('docent.ai.retrieval.candidate_limit', 24)),
        ]));
    }

    /** @param list<AiConversationTurn> $history */
    public function build(
        DocumentationContext $context,
        string $question = '',
        array $history = [],
        string $currentSlug = '',
        bool $widget = false,
    ): AiCorpus {
        $budget = max(1, (int) config('docent.ai.corpus_budget', 150000));
        $limit = $budget * 4;
        $retrieval = $this->retriever->retrieve($question, $context, $history, $currentSlug);
        $included = [];
        $citations = [];
        $characters = 0;
        $excerpted = 0;

        foreach ($retrieval->candidates as $candidate) {
            $page = $this->docent->page($candidate->record->slug);

            if ($page === null || ! $page->authorize($context)) {
                continue;
            }

            $url = $widget
                ? $this->docent->widgetUrl($candidate->record->slug)
                : $this->docent->fullUrl($candidate->record->slug);
            $separator = $included === [] ? '' : "\n\n";
            $markdown = trim($this->docent->agentMarkdown($page, $context));
            $block = $this->pageBlock($candidate->record->title, $url, $markdown);
            $remaining = $limit - $characters - strlen($separator);

            if (strlen($block) > $remaining) {
                $block = $this->boundedSectionBlock($candidate, $url, $remaining);
                $excerpted += $block === null ? 0 : 1;
            }

            if ($block === null || strlen($block) > $remaining) {
                continue;
            }

            $included[] = $block;
            $characters += strlen($separator.$block);
            $citations[] = [
                'slug' => $candidate->record->slug,
                'title' => $candidate->record->title,
                'url' => $url,
            ];
        }

        $omitted = count($retrieval->candidates) - count($included);
        $truncated = $omitted > 0 || $excerpted > 0;
        $content = implode("\n\n", $included);

        if ($content === '') {
            $content = $retrieval->candidates === []
                ? '[Docent notice: no relevant documentation was retrieved for this question.]'
                : '[Docent notice: relevant pages were found, but the configured corpus budget was reached before an excerpt could be included.]';
        } elseif ($truncated) {
            $content .= "\n\n[Docent notice: retrieval selected more relevant documentation than the configured corpus budget could include.]";
        }

        $version = $this->version($context, $widget);
        $diagnostics = [
            ...$retrieval->diagnostics,
            'included_count' => count($included),
            'excerpted_count' => $excerpted,
            'omitted_count' => $omitted,
            'estimated_tokens' => (int) ceil(strlen($content) / 4),
        ];
        $retrievalVersion = sha1(implode('|', [
            $version,
            mb_strtolower(trim($question)),
            $currentSlug,
            implode(',', array_column($citations, 'slug')),
            hash('sha256', $content),
        ]));

        return new AiCorpus(
            $content,
            $citations,
            $version,
            $retrievalVersion,
            (int) ceil(strlen($content) / 4),
            $truncated,
            $omitted,
            $diagnostics,
        );
    }

    private function boundedSectionBlock(AiRetrievalCandidate $candidate, string $url, int $remaining): ?string
    {
        $heading = trim((string) $candidate->section->title);
        $body = trim($candidate->section->body);

        if ($body === '') {
            return null;
        }

        $prefix = '<docent-page title="'.$this->attribute($candidate->record->title).'" url="'.$this->attribute($url).'" excerpt="true">'."\n";
        $headingLine = $heading === '' ? '' : '## '.$heading."\n\n";
        $suffix = "\n[Excerpt selected for relevance and bounded by the Assistant corpus budget.]\n</docent-page>";
        $available = $remaining - strlen($prefix.$headingLine.$suffix);

        if ($available < 80) {
            return null;
        }

        $excerpt = mb_strcut($body, 0, $available, 'UTF-8');

        return $prefix.$headingLine.rtrim($excerpt).$suffix;
    }

    private function pageBlock(string $title, string $url, string $markdown): string
    {
        return '<docent-page title="'.$this->attribute($title).'" url="'.$this->attribute($url).'">'."\n"
            .$markdown."\n</docent-page>";
    }

    private function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
