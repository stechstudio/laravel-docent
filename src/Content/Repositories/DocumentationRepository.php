<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\PageReference;

/**
 * Source of documentation content. Internal/experimental in v1 — the filesystem
 * implementation is the only supported driver, but the seam lets later versions
 * read from a database or remote store.
 */
interface DocumentationRepository
{
    public function find(string $slug): ?DocumentSource;

    /**
     * @return iterable<PageReference>
     */
    public function all(): iterable;

    /**
     * Resolve a reusable partial (from a `_partials/` directory) by name.
     */
    public function partial(string $name): ?DocumentSource;

    /**
     * Parsed `_group.yml` metadata for a directory, or null when absent.
     *
     * @return array{label?: string, description?: string, order?: int, icon?: string, locked?: bool, section?: bool}|null
     */
    public function groupMeta(string $directory): ?array;

    /**
     * A hash of every tracked file's path + mtime, for navigation/search cache
     * keys. Changes whenever any content file is added, removed, or edited.
     */
    public function directoryHash(): string;
}
