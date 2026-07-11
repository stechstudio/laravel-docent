<?php

declare(strict_types=1);

namespace STS\Docent\Content;

/**
 * The raw source of a documentation page (or partial): its slug, unparsed
 * markdown, a content hash for cache keying, and filesystem provenance.
 */
final class DocumentSource
{
    public function __construct(
        public readonly string $slug,
        public readonly string $rawContent,
        public readonly string $hash,
        public readonly string $path,
        public readonly int $lastModified,
    ) {}
}
