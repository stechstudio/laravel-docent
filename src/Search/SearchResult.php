<?php

declare(strict_types=1);

namespace STS\Docent\Search;

/**
 * A scored, viewer-visible search hit. The `snippet` is trusted HTML: the
 * matched terms are wrapped in `<mark>` after the surrounding text has already
 * been HTML-escaped. When the best match landed on a section heading,
 * `heading` carries its display text and `anchor` its slug, letting the UI
 * show the section title and deep-link to `#anchor`.
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
        public readonly ?string $anchor,
    ) {}

    /**
     * @return array{slug: string, url: string, title: string, group: string, snippet: string, heading: ?string, anchor: ?string}
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
            'anchor' => $this->anchor,
        ];
    }
}
