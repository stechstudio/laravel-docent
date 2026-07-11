<?php

declare(strict_types=1);

namespace STS\Docent\Content;

/**
 * The raw source of a documentation page (or partial), independent of where it
 * came from. `format` names the authoring format so the parsing layer can
 * dispatch (a future database repository may store Tiptap JSON); `baseDir` is
 * the directory relative links resolve against, decided by the repository —
 * the only layer that knows whether a slug is a section index. `path` is
 * provenance for error messages (a file path, a database identifier, …).
 */
final class DocumentSource
{
    public const FORMAT_MARKDOWN = 'markdown';

    public function __construct(
        public readonly string $slug,
        public readonly string $rawContent,
        public readonly string $hash,
        public readonly string $path,
        public readonly int $lastModified,
        public readonly string $baseDir = '',
        public readonly string $format = self::FORMAT_MARKDOWN,
    ) {}
}
