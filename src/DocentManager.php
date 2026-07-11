<?php

declare(strict_types=1);

namespace STS\Docent;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\DocentCache;

/**
 * The facade root and primary affordance layer. Applications register their
 * integrations here (conditions, values, links, components, audiences), and the
 * HTTP layer resolves pages, navigation, and per-request contexts through it.
 */
final class DocentManager
{
    public const VERSION = '0.1.0';

    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly DocumentationRepository $repository,
        private readonly DocumentParser $parser,
        private readonly DocentCache $cache,
        private readonly NavigationBuilder $navigation,
    ) {}

    /**
     * @param  Closure|class-string  $resolver
     */
    public function condition(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->registry->condition($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function value(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->registry->value($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function link(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->registry->link($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string|DocumentationComponent  $resolver
     */
    public function component(string $name, Closure|string|DocumentationComponent $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->registry->component($name, $resolver, $label, $description);

        return $this;
    }

    /**
     * @param  Closure|class-string  $resolver
     */
    public function audience(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->registry->audience($name, $resolver, $label, $description);

        return $this;
    }

    public function registry(): IntegrationRegistry
    {
        return $this->registry;
    }

    public function repository(): DocumentationRepository
    {
        return $this->repository;
    }

    public function page(string $slug): ?Page
    {
        $source = $this->repository->find($slug);

        return $source === null ? null : new Page($slug, $source, $this->document($source), $this);
    }

    /**
     * @return list<Navigation\NavigationItem|Navigation\NavigationGroup>
     */
    public function navigation(DocumentationContext $context): array
    {
        return $this->navigation->filtered($context);
    }

    /**
     * @return array{0: ?Navigation\NavigationItem, 1: ?Navigation\NavigationItem}
     */
    public function prevNext(string $slug, DocumentationContext $context): array
    {
        return $this->navigation->prevNext($slug, $context);
    }

    /**
     * Build the per-request context: the current viewer plus a Gate-backed
     * authorization closure.
     */
    public function contextFor(?Request $request): DocumentationContext
    {
        return new DocumentationContext(
            user: $request?->user() ?? Auth::user(),
            request: $request,
            gate: static fn (string $ability, array $arguments, ?Authenticatable $user): bool => $user !== null
                ? Gate::forUser($user)->allows($ability, $arguments)
                : Gate::allows($ability, $arguments),
        );
    }

    public function renderDocument(Document $document, DocumentationContext $context): string
    {
        $renderer = new HtmlRenderer(
            registry: $this->registry,
            context: $context,
            options: [
                'allow_html' => (bool) config('docent.content.allow_html', true),
                'debug' => (bool) config('app.debug', false),
            ],
            includeResolver: fn (string $name): ?Document => $this->partialDocument($name),
            urlResolver: fn (string $slug): string => $this->url($slug),
        );

        return $renderer->render($document);
    }

    public function partialDocument(string $name): ?Document
    {
        $source = $this->repository->partial($name);

        return $source === null ? null : $this->document($source);
    }

    public function audienceAllows(string $audience, DocumentationContext $context): bool
    {
        if ($context->audience !== null) {
            return $context->audience === $audience;
        }

        return $this->registry->resolveAudience($audience, $context) ?? false;
    }

    public function url(string $slug): string
    {
        return $slug === '' ? route('docent.home') : route('docent.show', $slug);
    }

    public function siteName(): string
    {
        return (string) config('docent.name');
    }

    public function clearCache(): void
    {
        $this->cache->bump();
    }

    /**
     * Parse a source's markdown into an AST, transparently cached by content
     * hash so a page is only parsed once per content revision.
     */
    private function document(DocumentSource $source): Document
    {
        return $this->cache->remember('ast:'.$source->hash, fn (): Document => $this->parser->parse($source->rawContent));
    }
}
