<?php

declare(strict_types=1);

namespace STS\Docent\Search;

/**
 * A single page's indexed, leak-safe search payload.
 *
 * Every field is a scalar or array of scalars so the whole index remains
 * `serialize()`-able for caching. The `body` is {@see SearchTextRenderer} output,
 * which already skips authorization/condition/audience subtrees, dynamic values,
 * and component output — nothing gated or per-request ever lands here. The record
 * carries only the page's own `authorize`/`audience` gate strings so the engine
 * can filter records per viewer at query time, exactly as page access does.
 */
final class SearchRecord
{
    /**
     * @param  list<array{title: string, slug: string}>  $headings
     * @param  list<string>  $keywords
     * @param  list<string>  $titleTokens
     * @param  list<string>  $descriptionTokens
     * @param  list<string>  $keywordTokens
     * @param  list<SearchSection>  $sections
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly ?string $description,
        public readonly array $headings,
        public readonly string $body,
        public readonly string $group,
        public readonly ?string $authorize,
        public readonly ?string $audience,
        public readonly array $keywords,
        public readonly array $titleTokens,
        public readonly array $descriptionTokens,
        public readonly array $keywordTokens,
        public readonly array $sections,
        public readonly int $order,
    ) {}
}
