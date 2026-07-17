<?php

declare(strict_types=1);

namespace STS\Docent\Content;

/**
 * A lightweight, front-matter-only view of a page, cheap enough to enumerate
 * every document for navigation, search indexing, and validation without a
 * full markdown parse. `$directory` is the page's relative source directory,
 * used to build the navigation tree (index.md pages collapse their slug, so
 * grouping cannot be derived from the slug alone).
 */
final class PageReference
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly ?int $order,
        public readonly bool $hidden,
        public readonly ?string $authorize,
        public readonly ?string $audience,
        public readonly bool $searchExcluded,
        public readonly ?string $description,
        public readonly string $directory = '',
        public readonly bool $locked = false,
        /** @var list<string> */
        public readonly array $searchKeywords = [],
        public readonly ?string $redirect = null,
        public readonly bool $redirectStub = false,
        public readonly string $layout = 'docs',
    ) {}
}
