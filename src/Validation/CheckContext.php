<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

use Closure;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\PageReference;
use STS\Docent\Content\Repositories\CompositeRepository;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
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
     * When set, the run is scoped to a single unsaved draft: {@see pages()}
     * yields only this slug (built from the draft's own front matter), and
     * {@see Document()} returns `$overrideDocument` for it instead of hitting the
     * repository. Passing an already-parsed document keeps the check
     * format-agnostic — the admin pre-parses a markdown or Tiptap draft and the
     * checks run identically. {@see slugSet()} still reflects every stored page
     * (plus the draft), so broken-link checks validate against the live tree.
     *
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
        private readonly ?string $overrideSlug = null,
        private readonly ?Document $overrideDocument = null,
    ) {}

    public function registry(): IntegrationRegistry
    {
        return $this->registry;
    }

    /**
     * The bound repository. Exposed so store-aware checks (e.g. shadowed pages)
     * can inspect a {@see CompositeRepository}.
     */
    public function repository(): DocumentationRepository
    {
        return $this->repository;
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
     * The pages the checks iterate. Normally every enumerable page; when scoped
     * to a draft (see the constructor), only that draft.
     *
     * @return list<PageReference>
     */
    public function pages(): array
    {
        if ($this->overrideSlug !== null) {
            return [$this->overrideReference()];
        }

        return $this->allReferences();
    }

    /**
     * The set of known page slugs, keyed for O(1) lookup. Home is the empty
     * slug. Always the full stored tree (plus the draft slug when scoped), so
     * broken-link checks resolve against every page a reader could reach.
     *
     * @return array<string, true>
     */
    public function slugSet(): array
    {
        $set = [];

        foreach ($this->allReferences() as $page) {
            $set[$page->slug] = true;
        }

        if ($this->overrideSlug !== null) {
            $set[$this->overrideSlug] = true;
        }

        return $set;
    }

    /** @return array<string, PageReference> */
    public function pageMap(): array
    {
        $pages = [];

        foreach ($this->allReferences() as $page) {
            $pages[$page->slug] = $page;
        }

        if ($this->overrideSlug !== null) {
            $pages[$this->overrideSlug] = $this->overrideReference();
        }

        return $pages;
    }

    /**
     * All enumerable pages (front-matter-only references), cached per run.
     *
     * @return list<PageReference>
     */
    private function allReferences(): array
    {
        return $this->pages ??= array_values([...$this->repository->all()]);
    }

    /**
     * The synthetic page reference for a scoped draft, derived from the parsed
     * draft's own front matter so page-level checks (authorize, directory-based
     * link resolution) behave exactly as they would once saved.
     */
    private function overrideReference(): PageReference
    {
        $frontMatter = $this->document((string) $this->overrideSlug)?->frontMatter() ?? new FrontMatter;

        $slug = (string) $this->overrideSlug;
        $redirectStub = $frontMatter->hasRedirect();

        return new PageReference(
            slug: $slug,
            title: $frontMatter->title() ?? '',
            order: $frontMatter->order(),
            hidden: $frontMatter->hidden() || $redirectStub,
            authorize: $frontMatter->authorize(),
            audience: $frontMatter->audience(),
            searchExcluded: $frontMatter->searchExcluded() || $redirectStub,
            description: $frontMatter->description(),
            directory: str_contains($slug, '/') ? substr($slug, 0, (int) strrpos($slug, '/')) : '',
            locked: false,
            searchKeywords: $frontMatter->searchKeywords(),
            redirect: $frontMatter->redirect(),
            redirectStub: $redirectStub,
        );
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

        if ($this->overrideSlug !== null && $slug === $this->overrideSlug) {
            return $this->documents[$slug] = $this->overrideDocument;
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
