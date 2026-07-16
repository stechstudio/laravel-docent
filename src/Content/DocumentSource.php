<?php

declare(strict_types=1);

namespace STS\Docent\Content;

/**
 * The raw source of a documentation page (or partial), independent of where it
 * came from. `format` names the authoring format so the parsing layer can
 * dispatch (a future database repository may store Tiptap JSON); `baseDir` is
 * the directory relative links resolve against, decided by the repository —
 * the only layer that knows whether a slug is a section index. `path` is
 * provenance for error messages (a file path, a database identifier, …), while
 * `origin` carries the security boundary used for raw HTML rendering.
 */
final class DocumentSource
{
    public const FORMAT_MARKDOWN = 'markdown';

    public const FORMAT_TIPTAP = 'tiptap';

    public const ORIGIN_REPOSITORY = 'repository';

    public const ORIGIN_DATABASE = 'database';

    /**
     * @param  self::FORMAT_*  $format
     * @param  array<string, mixed>|null  $frontMatter  When set, overrides the
     *                                                  document's parsed front matter. Tiptap sources carry no front
     *                                                  matter inside the JSON body — it lives in the `docent_pages`
     *                                                  column — so the repository supplies it here and the parsing layer
     *                                                  applies it. Markdown sources leave this null (front matter is in
     *                                                  the raw content, parsed as usual).
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $rawContent,
        public readonly string $hash,
        public readonly string $path,
        public readonly int $lastModified,
        public readonly string $baseDir = '',
        public readonly string $format = self::FORMAT_MARKDOWN,
        public readonly ?array $frontMatter = null,
        public readonly string $origin = self::ORIGIN_REPOSITORY,
    ) {}
}
