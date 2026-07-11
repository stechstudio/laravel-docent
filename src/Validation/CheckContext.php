<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

use Closure;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\PageReference;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Runtime\IntegrationRegistry;
use Throwable;

/**
 * Shared state for a single `docent:check` run. Enumerates page metadata once
 * and parses documents/partials lazily, caching them for the duration of the
 * run so checks that share an AST only pay to parse it once.
 *
 * Framework touchpoints (named-route existence, gate/ability existence) are
 * injected as closures so the whole validation layer stays unit-testable.
 */
final class CheckContext
{
    /** @var ?list<PageReference> */
    private ?array $pages = null;

    /** @var array<string, ?Document> */
    private array $documents = [];

    /** @var array<string, ?Document> */
    private array $partials = [];

    /**
     * @param  Closure(string): bool  $routeExists
     * @param  Closure(string): bool  $abilityExists
     */
    public function __construct(
        private readonly DocumentationRepository $repository,
        private readonly DocumentParser $parser,
        private readonly IntegrationRegistry $registry,
        private readonly string $docsPath,
        private readonly string $publicPath,
        private readonly string $routePrefix,
        private readonly Closure $routeExists,
        private readonly Closure $abilityExists,
    ) {}

    public function registry(): IntegrationRegistry
    {
        return $this->registry;
    }

    public function docsPath(): string
    {
        return $this->docsPath;
    }

    public function publicPath(): string
    {
        return $this->publicPath;
    }

    public function routePrefix(): string
    {
        return $this->routePrefix;
    }

    public function routeExists(string $name): bool
    {
        return ($this->routeExists)($name);
    }

    public function abilityExists(string $ability): bool
    {
        return ($this->abilityExists)($ability);
    }

    /**
     * All enumerable pages (front-matter-only references).
     *
     * @return list<PageReference>
     */
    public function pages(): array
    {
        return $this->pages ??= array_values([...$this->repository->all()]);
    }

    /**
     * The set of known page slugs, keyed for O(1) lookup. Home is the empty slug.
     *
     * @return array<string, true>
     */
    public function slugSet(): array
    {
        $set = [];

        foreach ($this->pages() as $page) {
            $set[$page->slug] = true;
        }

        return $set;
    }

    public function source(string $slug): ?DocumentSource
    {
        return $this->repository->find($slug);
    }

    /**
     * Parse a page's AST, caching per run. Returns null when the source is
     * missing or fails to parse (e.g. malformed front matter) — the parse
     * boundary is the one place a catch is warranted, and the front-matter
     * check reports the underlying error loudly.
     */
    public function document(string $slug): ?Document
    {
        if (array_key_exists($slug, $this->documents)) {
            return $this->documents[$slug];
        }

        $source = $this->repository->find($slug);

        return $this->documents[$slug] = $source === null
            ? null
            : $this->parse($source->rawContent);
    }

    public function partialSource(string $name): ?DocumentSource
    {
        return $this->repository->partial($name);
    }

    public function hasPartial(string $name): bool
    {
        return $this->repository->partial($name) !== null;
    }

    /**
     * Parse a partial's AST, caching per run. Returns null when absent or
     * unparseable.
     */
    public function partial(string $name): ?Document
    {
        if (array_key_exists($name, $this->partials)) {
            return $this->partials[$name];
        }

        $source = $this->repository->partial($name);

        return $this->partials[$name] = $source === null
            ? null
            : $this->parse($source->rawContent);
    }

    private function parse(string $content): ?Document
    {
        try {
            return $this->parser->parse($content);
        } catch (Throwable) {
            return null;
        }
    }
}
