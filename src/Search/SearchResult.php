<?php

declare(strict_types=1);

namespace STS\Docent\Search;

/**
 * A scored, viewer-visible search hit. The `snippet` is trusted HTML: the
 * matched terms are wrapped in `<mark>` after the surrounding text has already
 * been HTML-escaped. `heading` is the anchor slug of the best-matching heading
 * (or null), letting the UI deep-link to `#heading`.
 */
final class SearchResult
{
    public function __construct(
        public readonly string $slug,
        public readonly string $url,
        public readonly string $title,
        public readonly string $group,
        public readonly string $snippet,
        public readonly ?string $heading,
    ) {}

    /**
     * @return array{slug: string, url: string, title: string, group: string, snippet: string, heading: ?string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'url' => $this->url,
            'title' => $this->title,
            'group' => $this->group,
            'snippet' => $this->snippet,
            'heading' => $this->heading,
        ];
    }
}
