<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use STS\Docent\DocentManager;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;
use STS\Docent\Search\SearchMatch;
use STS\Docent\Search\SearchRecord;
use STS\Docent\Search\SearchSection;

/** Selects viewer-visible documentation for one Assistant question. */
final class AiRetriever
{
    private const FOLLOW_UP_PATTERN = '/\b(it|its|that|this|those|them|there|then|after|before|next|also|more|same)\b/i';

    public function __construct(
        private readonly SearchEngine $search,
        private readonly SearchIndexer $indexer,
        private readonly DocentManager $docent,
    ) {}

    /** @param list<AiConversationTurn> $history */
    public function retrieve(
        string $question,
        DocumentationContext $context,
        array $history = [],
        string $currentSlug = '',
    ): AiRetrieval {
        $candidateLimit = max(1, (int) config('docent.ai.retrieval.candidate_limit', 24));
        $pageLimit = max(1, (int) config('docent.ai.retrieval.max_pages', 8));
        $direct = $this->search->ranked($question, $context, $candidateLimit);
        $ranked = [];

        foreach ($direct as $match) {
            $this->merge($ranked, $match, $match->score, 'question');
        }

        $followUp = $history !== [] && $this->looksLikeFollowUp($question, $direct);
        if ($followUp) {
            $last = $history[array_key_last($history)];
            foreach ($this->search->ranked($last->question, $context, $candidateLimit) as $match) {
                $this->merge($ranked, $match, $match->score * 0.45, 'conversation');
            }
        }

        if ($currentSlug !== '') {
            $record = $this->visibleRecord($currentSlug, $context);

            if ($record !== null) {
                $top = $direct[0]->score ?? 0.0;
                $boost = max(1.0, $top * 0.25);
                $section = $ranked[$record->slug]['section'] ?? $record->sections[0] ?? null;

                if ($section !== null) {
                    $match = new SearchMatch($record, $section, $boost);
                    $this->merge($ranked, $match, $boost, 'current_page');
                }
            }
        }

        $candidates = array_values(array_map(
            static fn (array $candidate): AiRetrievalCandidate => new AiRetrievalCandidate(
                $candidate['record'],
                $candidate['section'],
                $candidate['score'],
                array_keys($candidate['reasons']),
            ),
            $ranked,
        ));

        usort($candidates, static fn (AiRetrievalCandidate $left, AiRetrievalCandidate $right): int => ($right->score <=> $left->score)
                ?: ($left->record->order <=> $right->record->order)
                ?: strcmp($left->record->slug, $right->record->slug));

        $candidates = array_slice($candidates, 0, $pageLimit);

        $currentIncluded = array_filter(
            $candidates,
            static fn (AiRetrievalCandidate $candidate): bool => in_array('current_page', $candidate->reasons, true),
        ) !== [];

        return new AiRetrieval($candidates, [
            'candidate_count' => count($ranked),
            'selected_count' => count($candidates),
            'follow_up_context_used' => $followUp,
            'current_page_included' => $currentIncluded,
            'selected' => array_map(static fn (AiRetrievalCandidate $candidate): array => [
                'slug' => $candidate->record->slug,
                'section' => $candidate->section->slug,
                'score' => round($candidate->score, 4),
                'reasons' => $candidate->reasons,
            ], $candidates),
        ]);
    }

    /**
     * @param  array<string, array{record: SearchRecord, section: SearchSection, score: float, reasons: array<string, true>}>  $ranked
     */
    private function merge(array &$ranked, SearchMatch $match, float $score, string $reason): void
    {
        $existing = $ranked[$match->record->slug] ?? null;

        if ($existing === null) {
            $ranked[$match->record->slug] = [
                'record' => $match->record,
                'section' => $match->section,
                'score' => $score,
                'reasons' => [$reason => true],
            ];

            return;
        }

        $ranked[$match->record->slug]['score'] += $score;
        $ranked[$match->record->slug]['reasons'][$reason] = true;

        if ($match->score > $existing['score']) {
            $ranked[$match->record->slug]['section'] = $match->section;
        }
    }

    /** @param list<SearchMatch> $direct */
    private function looksLikeFollowUp(string $question, array $direct): bool
    {
        if (preg_match(self::FOLLOW_UP_PATTERN, $question) === 1) {
            return true;
        }

        preg_match_all('/[\pL\pN]+/u', $question, $words);

        return count($words[0]) <= 4 || $direct === [];
    }

    private function visibleRecord(string $slug, DocumentationContext $context): ?SearchRecord
    {
        foreach ($this->indexer->records() as $record) {
            if ($record->slug === $slug
                && $this->docent->authorizes($record->authorize, $record->audience, $context)) {
                return $record;
            }
        }

        return null;
    }
}
