<?php

declare(strict_types=1);

namespace STS\Docent\Content\Repositories;

use Illuminate\Support\Str;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\PageReference;
use STS\Docent\Documents\FrontMatter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Reads documentation from a directory of markdown files.
 *
 * - Slug = relative path minus extension; `index.md` collapses to its directory
 *   slug (root `index.md` → the empty-string home slug).
 * - Any path segment beginning with `_` (e.g. `_partials/`, `_drafts/`) and any
 *   file beginning with `_` is excluded from pages; `_partials/` files are
 *   reachable as includes via {@see partial()}.
 */
final class FilesystemRepository implements DocumentationRepository, LockAwareRepository
{
    public function __construct(
        private readonly string $path,
    ) {}

    public function find(string $slug): ?DocumentSource
    {
        $relative = $this->pageMap()[$slug] ?? null;

        return $relative === null ? null : $this->source($slug, $relative);
    }

    public function all(): iterable
    {
        foreach ($this->pageMap() as $slug => $relative) {
            yield $this->reference($slug, $relative);
        }
    }

    public function partial(string $name): ?DocumentSource
    {
        $relative = $this->partialMap()[$name] ?? null;

        return $relative === null ? null : $this->source($name, $relative);
    }

    public function pageLocked(string $slug): bool
    {
        $relative = $this->pageMap()[$slug] ?? null;

        return $relative !== null && $this->relativeLocked($relative);
    }

    public function partialLocked(string $name): bool
    {
        $relative = $this->partialMap()[$name] ?? null;

        return $relative !== null && $this->relativeLocked($relative);
    }

    public function groupMeta(string $directory): ?array
    {
        $file = $this->path.'/'.$directory.'/_group.yml';

        if (! is_file($file)) {
            return null;
        }

        $data = Yaml::parseFile($file);

        return is_array($data) ? $data : null;
    }

    public function directoryHash(): string
    {
        $entries = [];

        foreach ($this->trackedFiles() as $relative) {
            $entries[] = $relative.':'.filemtime($this->path.'/'.$relative);
        }

        return sha1(implode('|', $entries));
    }

    /**
     * @return array<string, string> slug => relative path
     */
    private function pageMap(): array
    {
        $map = [];

        foreach ($this->markdownFiles() as $relative) {
            if ($this->isUnderscored($relative)) {
                continue;
            }

            $map[$this->slugOf($relative)] = $relative;
        }

        return $map;
    }

    /**
     * @return array<string, string> partial name => relative path
     */
    private function partialMap(): array
    {
        $map = [];

        foreach ($this->markdownFiles() as $relative) {
            $position = strpos($relative, '_partials/');

            if ($position === false) {
                continue;
            }

            $name = substr($relative, $position + strlen('_partials/'), -3);
            $map[$name] = $relative;
        }

        return $map;
    }

    private function reference(string $slug, string $relative): PageReference
    {
        $frontMatter = new FrontMatter($this->frontMatterOf($relative));

        return new PageReference(
            slug: $slug,
            title: $frontMatter->title() ?? $this->deriveTitle($slug),
            order: $frontMatter->order(),
            hidden: $frontMatter->hidden(),
            authorize: $frontMatter->authorize(),
            audience: $frontMatter->audience(),
            searchExcluded: $frontMatter->searchExcluded(),
            description: $frontMatter->description(),
            directory: $this->directoryOf($relative),
            locked: $this->relativeLocked($relative),
            searchKeywords: $frontMatter->searchKeywords(),
        );
    }

    private function relativeLocked(string $relative): bool
    {
        if ((new FrontMatter($this->frontMatterOf($relative)))->locked()) {
            return true;
        }

        $directory = $this->directoryOf($relative);

        while (true) {
            if (($this->groupMeta($directory)['locked'] ?? false) === true) {
                return true;
            }

            if ($directory === '') {
                return false;
            }

            $parent = str_replace('\\', '/', dirname($directory));
            $directory = $parent === '.' ? '' : $parent;
        }
    }

    private function source(string $slug, string $relative): DocumentSource
    {
        $absolute = $this->path.'/'.$relative;
        $content = (string) file_get_contents($absolute);

        return new DocumentSource(
            slug: $slug,
            rawContent: $content,
            hash: sha1($content),
            path: $absolute,
            lastModified: (int) filemtime($absolute),
            baseDir: basename($relative) === 'index.md' ? $slug : $this->directoryOf($relative),
        );
    }

    /**
     * Cheap front-matter-only parse: split on the closing `---` and hand the
     * YAML block to symfony/yaml without a full markdown parse.
     *
     * @return array<string, mixed>
     */
    private function frontMatterOf(string $relative): array
    {
        $content = (string) file_get_contents($this->path.'/'.$relative);

        if (! str_starts_with($content, '---')) {
            return [];
        }

        if (preg_match('/^---\R(.*?)\R---\s*(?:\R|$)/s', $content, $matches) !== 1) {
            return [];
        }

        // Parsing author-supplied YAML is an external boundary: a malformed block
        // must not abort enumeration. `docent:check`'s front-matter check re-parses
        // and reports the error loudly; here we degrade to empty front matter.
        try {
            $data = Yaml::parse($matches[1]);
        } catch (ParseException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private function slugOf(string $relative): string
    {
        $segments = explode('/', substr($relative, 0, -3));

        if (end($segments) === 'index') {
            array_pop($segments);
        }

        return implode('/', $segments);
    }

    private function directoryOf(string $relative): string
    {
        $directory = str_replace('\\', '/', dirname($relative));

        return $directory === '.' ? '' : $directory;
    }

    private function deriveTitle(string $slug): string
    {
        return $slug === '' ? 'Home' : Str::headline(Str::afterLast($slug, '/'));
    }

    private function isUnderscored(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if (str_starts_with($segment, '_')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function markdownFiles(): array
    {
        return $this->scan('*.md');
    }

    /**
     * @return list<string>
     */
    private function trackedFiles(): array
    {
        return $this->scan('*.md', '*.yml');
    }

    /**
     * @return list<string>
     */
    private function scan(string ...$patterns): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $relatives = [];

        foreach (Finder::create()->files()->name($patterns)->in($this->path) as $file) {
            $relatives[] = str_replace('\\', '/', $file->getRelativePathname());
        }

        sort($relatives);

        return $relatives;
    }
}
