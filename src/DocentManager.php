<?php

declare(strict_types=1);

namespace STS\Docent;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Ast\SectionCards;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\HtmlPolicy;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Renderer\CodeBlockRenderer;
use STS\Docent\Documents\Renderer\ContentHtmlSanitizer;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\SectionCardsHtml;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Navigation\NavigationItem;
use STS\Docent\Navigation\NavigationLink;
use STS\Docent\Navigation\NavigationSection;
use STS\Docent\Navigation\SectionCard;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Sites\SiteConfig;
use STS\Docent\Sites\SiteRef;
use STS\Docent\Support\DocentCache;
use STS\Docent\Support\GrayPalette;
use STS\Docent\Support\Icon;
use STS\Docent\Support\InternalLink;
use STS\Docent\Support\RadiusScale;

/**
 * The facade root and primary affordance layer. Applications register their
 * integrations here (conditions, values, links, components, audiences), and the
 * HTTP layer resolves pages, navigation, and per-request contexts through it.
 */
final class DocentManager
{
    public const VERSION = '0.1.0';

    public const MAX_REDIRECT_HOPS = 3;

    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly DocumentationRepository $repository,
        private readonly DocumentParser $parser,
        private readonly DocentCache $cache,
        private readonly NavigationBuilder $navigation,
        private readonly CodeBlockRenderer $codeBlockRenderer,
        private readonly DocumentationMode $mode,
        private readonly ContentHtmlSanitizer $htmlSanitizer,
        private readonly SiteConfig $siteConfig,
    ) {}

    /** @var array<string, string> */
    private array $assetVersions = [];

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

    /** @param list<string> $slugs */
    public function suggest(string $pattern, array $slugs): self
    {
        $this->registry->suggest($pattern, $slugs);

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
     * Resolve a redirect stub to its final page without revealing a missing or
     * unauthorized destination. Authors are expected to flatten chains; the
     * hard hop limit keeps malformed database-authored redirects bounded.
     */
    public function redirectTarget(Page $stub, DocumentationContext $context): ?Page
    {
        if (! $stub->isRedirect()) {
            return null;
        }

        $current = $stub;
        $visited = [$stub->slug => true];

        for ($hop = 0; $hop < self::MAX_REDIRECT_HOPS; $hop++) {
            $slug = $this->validRedirectSlug($current->redirect());

            if ($slug === null || isset($visited[$slug])) {
                return null;
            }

            $target = $this->page($slug);

            if ($target === null || ! $target->authorize($context)) {
                return null;
            }

            if (! $target->isRedirect()) {
                return $target;
            }

            $visited[$slug] = true;
            $current = $target;
        }

        return null;
    }

    /**
     * @return list<NavigationItem|NavigationGroup>
     */
    public function navigation(DocumentationContext $context): array
    {
        return $this->navigation->filtered($context);
    }

    /**
     * @return list<NavigationSection>
     */
    public function navigationSections(DocumentationContext $context, string $currentSlug = ''): array
    {
        return $this->navigation->sections($context, $currentSlug);
    }

    /**
     * @return list<NavigationItem|NavigationGroup>
     */
    public function sectionNavigation(string $slug, DocumentationContext $context): array
    {
        return $this->navigation->sectionNavigation($slug, $context);
    }

    /**
     * @return list<NavigationLink>
     */
    public function navigationLinks(DocumentationContext $context, string $currentSlug = ''): array
    {
        return $this->navigation->links($context, $currentSlug);
    }

    /**
     * @return list<NavigationLink>
     */
    public function topbarLinks(DocumentationContext $context, string $currentSlug = ''): array
    {
        return $this->navigation->topbarLinks($context, $currentSlug);
    }

    /**
     * @return array{0: ?NavigationItem, 1: ?NavigationItem}
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
            site: $this->siteRef(),
        );
    }

    /**
     * The context of an anonymous visitor, regardless of who is browsing —
     * public surfaces (sitemap) must never widen to the current viewer.
     */
    public function guestContext(): DocumentationContext
    {
        return new DocumentationContext(
            user: null,
            request: null,
            gate: static fn (string $ability, array $arguments, ?Authenticatable $user): bool => Gate::forUser(null)->allows($ability, $arguments),
            site: $this->siteRef(),
        );
    }

    /**
     * Resolve the Blade view for a front-matter `layout` value. Anything but
     * the default `docs` layout resolves through the host's `docent.layouts`
     * config map first, then the `docent::layouts.*` namespace — where hosts
     * may add brand-new views (`resources/views/vendor/docent/layouts/`)
     * without forking any package file. An unknown layout fails loudly when
     * the view is rendered rather than silently falling back.
     */
    public function layoutView(string $layout): string
    {
        $configured = $this->config('layouts.'.$layout);

        return is_string($configured) && $configured !== '' ? $configured : 'docent::layouts.'.$layout;
    }

    public function renderDocument(Document $document, DocumentationContext $context, string $baseDir = ''): string
    {
        $renderer = new HtmlRenderer(
            registry: $this->registry,
            context: $context,
            options: [
                'allow_html' => (bool) $this->config('content.allow_html', true),
                'debug' => (bool) config('app.debug', false),
                'base_dir' => $baseDir,
                'route_prefix' => (string) $this->config('route.prefix', 'docs'),
            ],
            includeResolver: fn (string $name): ?Document => $this->partialDocument($name),
            urlResolver: fn (string $slug): string => $this->url($slug),
            sectionCardsRenderer: fn (SectionCards $node): string => $this->sectionCardsHtml($node->section, $node->columns, $context),
            codeBlockRenderer: $this->codeBlockRenderer,
            htmlSanitizer: $this->htmlSanitizer,
        );

        return $renderer->render($document);
    }

    /**
     * Card summaries for a directory's children (every top-level directory
     * when `$section` is empty), filtered to what the viewer may see.
     *
     * @return list<SectionCard>
     */
    public function sectionCards(string $section, DocumentationContext $context): array
    {
        return $this->navigation->cards($section, $context);
    }

    public function sectionCardsHtml(string $section, int $columns, DocumentationContext $context): string
    {
        return SectionCardsHtml::render($this->sectionCards($section, $context), $columns);
    }

    public function markdownUrl(string $slug): string
    {
        return $this->route('show', ['slug' => ($slug === '' ? 'index' : $slug).'.md']);
    }

    public function llmsUrl(bool $full = false): string
    {
        return $this->route($full ? 'llms-full' : 'llms');
    }

    public function siteDescription(): string
    {
        $description = $this->config('description');

        return is_string($description) && trim($description) !== ''
            ? trim($description)
            : 'Application guides and help documentation for '.$this->siteName().'.';
    }

    /**
     * The absolute social-preview image URL for a page: a front-matter `image`
     * wins over the shared `seo.image`, and app-relative paths resolve against
     * the application URL so crawlers always receive an absolute URL. Null
     * when neither is set — link unfurls fall back to text.
     */
    public function seoImage(?string $pageImage = null): ?string
    {
        $image = $pageImage ?? $this->config('seo.image');

        if (! is_string($image) || trim($image) === '') {
            return null;
        }

        $image = trim($image);

        return preg_match('#^(?:https?:)?//#i', $image) === 1 ? $image : url($image);
    }

    /** @return array<string, mixed> */
    public function widgetConfig(): array
    {
        $mode = $this->config('widget.mode') === 'push' ? 'push' : 'overlay';
        $position = $this->config('widget.position') === 'left' ? 'left' : 'right';
        $launcher = $this->config('widget.launcher') === 'none' ? 'none' : 'button';
        $offset = max(0, (int) $this->config('widget.offset', 24));
        $icon = (string) $this->config('widget.icon', 'book-open');

        if (Icon::has($icon)) {
            $iconMarkup = Icon::svg($icon);
        } elseif (str_starts_with($icon, '/') || preg_match('#^https?://#i', $icon) === 1) {
            $iconMarkup = '<img src="'.e($icon).'" alt="" />';
        } else {
            $iconMarkup = Icon::svg('book-open');
        }

        return [
            'docsUrl' => $this->fullUrl(''),
            'widgetUrl' => $this->widgetUrl(),
            'suggestionsUrl' => $this->route('widget.suggestions'),
            'page' => Route::currentRouteName(),
            'assetUrl' => $this->asset('docent-widget.js'),
            'mode' => $mode,
            'position' => $position,
            'offset' => $offset,
            'launcher' => $launcher,
            'preload' => (bool) $this->config('widget.preload', false),
            'icon' => $iconMarkup,
            'accent' => $this->accent(),
            'strings' => [
                'help' => __('docent::ui.widget.help'),
                'documentation' => __('docent::ui.common.documentation'),
            ],
        ];
    }

    /**
     * Resolve contextual suggestions without exposing missing or unauthorized
     * pages to the current viewer.
     *
     * @return list<array{slug: string, title: string, description: ?string, url: string}>
     */
    public function widgetSuggestions(string $hostPage, DocumentationContext $context): array
    {
        return $this->authorizedSuggestions($this->registry->suggestionsFor(trim($hostPage)), $context);
    }

    /**
     * Filter explicit slugs (e.g. a client-side override) through the same
     * authorization gate, so no suggestion surface can leak a gated page.
     *
     * @param  list<string>  $slugs
     * @return list<array{slug: string, title: string, description: ?string, url: string}>
     */
    public function authorizedSuggestions(array $slugs, DocumentationContext $context): array
    {
        $suggestions = [];

        foreach (array_slice(array_values(array_unique($slugs)), 0, 5) as $slug) {
            $page = $this->page($slug);

            if ($page === null || $page->isRedirect() || ! $page->authorize($context)) {
                continue;
            }

            $suggestions[] = [
                'slug' => $slug,
                'title' => $page->title(),
                'description' => $page->description(),
                'url' => $this->widgetUrl($slug),
            ];
        }

        return $suggestions;
    }

    public function viewerFingerprint(DocumentationContext $context): string
    {
        $slugs = array_map(
            static fn (NavigationItem $item): string => $item->slug,
            $this->navigation->flatten($this->navigation($context)),
        );
        $user = $context->user;
        $identifier = $user === null ? 'guest' : get_class($user).':'.(string) $user->getAuthIdentifier();
        $parameters = json_encode($context->parameters, JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '';

        return sha1(implode('|', $slugs).'|'.$identifier.'|'.($context->audience ?? '').'|'.$parameters);
    }

    /**
     * Namespace browser-only Assistant state to this application, Laravel
     * session, viewer scope, and reader surface. The opaque value is safe to
     * render, while a session or permission change makes prior state
     * unreachable without exposing any of the binding inputs.
     */
    public function assistantStateNamespace(Request $request, DocumentationContext $context, bool $widget = false): string
    {
        $key = (string) config('app.key', 'docent');
        $session = 'no-session';

        if ($request->hasSession()) {
            $store = $request->session();
            $nonce = $store->get('docent.assistant_state_nonce');

            if (! is_string($nonce) || $nonce === '') {
                $nonce = Str::random(32);
                $store->put('docent.assistant_state_nonce', $nonce);
            }

            $session = $store->getId().':'.$nonce;
        }

        return hash_hmac('sha256', implode('|', [
            $session,
            $this->viewerFingerprint($context),
            $widget ? 'widget' : 'reader',
        ]), $key === '' ? 'docent' : $key);
    }

    public function partialDocument(string $name): ?Document
    {
        $source = $this->repository->partial($name);

        return $source === null ? null : $this->document($source);
    }

    /**
     * Whether a viewer may see content gated by the given front matter
     * `authorize` (gate/ability) and `audience`. The single source of truth for
     * page-level access, shared by {@see Page} and search so a hit can never
     * surface a page the viewer could not open.
     */
    public function authorizes(?string $authorize, ?string $audience, DocumentationContext $context): bool
    {
        if ($authorize !== null && ! $context->can($authorize)) {
            return false;
        }

        if ($audience !== null && ! $this->audienceAllows($audience, $context)) {
            return false;
        }

        return true;
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
        if ($this->mode->widget()) {
            return $this->widgetUrl($slug);
        }

        return $this->fullUrl($slug);
    }

    public function fullUrl(string $slug): string
    {
        return $slug === '' ? $this->route('home') : $this->route('show', [$slug]);
    }

    public function widgetUrl(string $slug = ''): string
    {
        return $slug === ''
            ? $this->route('widget.home')
            : $this->route('widget.show', ['slug' => $slug]);
    }

    public function enableWidgetMode(): void
    {
        $this->mode->enableWidget();
    }

    /**
     * Resolve a link destination the way the renderer does: slug-style and
     * docs-rooted destinations become page URLs; external URLs pass through
     * verbatim. Shared with landing-page CTA resolution so a front-matter href
     * behaves exactly like an in-body markdown link.
     */
    public function resolveUrl(string $destination, string $baseDir = ''): string
    {
        $target = InternalLink::resolve($destination, $baseDir, (string) $this->config('route.prefix', 'docs'));

        return $target === null ? $destination : $this->url($target['slug']).$target['suffix'];
    }

    /**
     * The configured site name, falling back to a headline of the site key
     * (`admin-docs` → "Admin Docs") so a site without a `name` never renders
     * an empty wordmark or a broken llms.txt heading.
     */
    public function siteName(): string
    {
        $name = $this->config('name');

        return is_string($name) && trim($name) !== '' ? $name : Str::headline($this->key());
    }

    /**
     * Configuration and route-name seams. These become site-aware when the
     * multi-site registry takes ownership of manager construction.
     */
    public function config(string $path, mixed $default = null): mixed
    {
        return $this->siteConfig->get($path, $default);
    }

    public function key(): string
    {
        return $this->siteConfig->key;
    }

    public function siteRef(): SiteRef
    {
        return new SiteRef($this->key(), $this->siteName());
    }

    public function routeName(string $suffix): string
    {
        return 'docent.'.$this->key().'.'.$suffix;
    }

    /** @param array<string|int, mixed> $parameters */
    public function route(string $suffix, array $parameters = []): string
    {
        return route($this->routeName($suffix), $parameters);
    }

    /**
     * The accent colour driving the whole UI, from `docent.theme.accent`.
     */
    public function accent(): string
    {
        return (string) $this->config('theme.accent', '#0284c7');
    }

    /**
     * The configured logo (path or URL), or null to fall back to a wordmark.
     */
    public function logo(): ?string
    {
        return $this->themeString('logo');
    }

    /**
     * The dark-mode logo, or null to reuse {@see logo()} in both modes. The
     * header swaps between them via CSS, so there is no theme flash.
     */
    public function logoDark(): ?string
    {
        return $this->themeString('logo_dark');
    }

    /**
     * A square mark shown in the compact/mobile header, or null to keep the
     * full logo/wordmark at every size.
     */
    public function logomark(): ?string
    {
        return $this->themeString('logomark');
    }

    /**
     * The favicon (path or URL), emitted as <link rel="icon"> when set.
     */
    public function favicon(): ?string
    {
        return $this->themeString('favicon');
    }

    /**
     * Optional webfont stylesheet URL (Bunny/Google …). Null keeps the default
     * of zero external requests.
     */
    public function fontHref(): ?string
    {
        return $this->themeString('font.href');
    }

    /**
     * The complete contents of the dynamic <style> block: the accent, any
     * configured font stacks, and the gray/radius palette remaps. Emitted after
     * the built stylesheet so these host overrides always win the cascade.
     */
    public function themeStyles(): string
    {
        $declarations = '--docent-accent:'.$this->accent().';';

        if (($sans = $this->themeString('font.sans')) !== null) {
            $declarations .= '--font-sans:'.$sans.';';
        }

        if (($mono = $this->themeString('font.mono')) !== null) {
            $declarations .= '--font-mono:'.$mono.';';
        }

        $declarations .= GrayPalette::fromConfig($this->themeString('gray'))->declarations();
        $declarations .= RadiusScale::fromConfig($this->themeString('radius'))->declarations();

        return ':root{'.$declarations.'}';
    }

    private function themeString(string $key): ?string
    {
        $value = $this->config('theme.'.$key);

        return $value === null ? null : (string) $value;
    }

    /**
     * URL for a shipped asset. Prefers a host-published copy under
     * `public/vendor/docent` when present, otherwise the fallback asset route.
     * Both carry a content hash so caches bust the moment the file changes.
     */
    public function asset(string $file): string
    {
        $published = public_path('vendor/docent/'.$file);

        if (is_file($published)) {
            return asset('vendor/docent/'.$file).'?v='.$this->assetVersion($published);
        }

        return $this->route('asset', ['file' => $file]).'?v='.$this->assetVersion($this->assetPath($file));
    }

    public function assetPath(string $file): string
    {
        return __DIR__.'/../resources/dist/'.$file;
    }

    /**
     * The group label for the group containing the given page slug — the
     * breadcrumb shown above a page title. Null for ungrouped/home pages.
     */
    public function breadcrumb(string $slug, DocumentationContext $context): ?string
    {
        foreach ($this->navigation($context) as $node) {
            if ($node instanceof NavigationGroup && $node->contains($slug)) {
                return $node->label;
            }
        }

        return null;
    }

    private function assetVersion(string $path): string
    {
        return $this->assetVersions[$path] ??= is_file($path) ? substr(md5_file($path), 0, 10) : 'dev';
    }

    public function clearCache(): void
    {
        $this->cache->bump();
    }

    private function validRedirectSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        $slug = trim($slug);

        return $slug !== '' && preg_match('#^[a-z0-9]([a-z0-9/-]*[a-z0-9])?$#', $slug) === 1
            ? $slug
            : null;
    }

    /**
     * Parse a source into an AST, transparently cached by format + content hash
     * so a page is only parsed once per content revision. `DocumentSource::format`
     * dispatches to the matching parser (markdown or Tiptap JSON).
     *
     * Tiptap sources carry their metadata out-of-band (the JSON body has none),
     * so the repository supplies a {@see DocumentSource::$frontMatter} override
     * that is re-applied after the (front-matter-free) body is pulled from cache
     * — keeping the cached AST content-addressed while a metadata-only edit
     * still lands the right front matter.
     */
    private function document(DocumentSource $source): Document
    {
        $parser = match ($source->format) {
            DocumentSource::FORMAT_MARKDOWN => $this->parser,
            DocumentSource::FORMAT_TIPTAP => new TiptapDocumentParser,
        };

        $document = $this->cache->remember(
            'ast:'.$source->format.':'.$source->hash,
            fn (): Document => $parser->parse($source->rawContent),
        );

        $document = $source->frontMatter === null
            ? $document
            : $document->withFrontMatter($source->frontMatter);

        return $document->withHtmlPolicy($this->sourceHtmlPolicy($source));
    }

    private function sourceHtmlPolicy(DocumentSource $source): HtmlPolicy
    {
        return $source->origin === DocumentSource::ORIGIN_DATABASE
            ? $this->databaseHtmlPolicy()
            : ((bool) $this->config('content.allow_html', true) ? HtmlPolicy::Trusted : HtmlPolicy::Denied);
    }

    /** The HTML policy database-authored content renders under. */
    public function databaseHtmlPolicy(): HtmlPolicy
    {
        return (bool) $this->config('content.database.sanitize_html', true)
            ? HtmlPolicy::Sanitized
            : HtmlPolicy::Trusted;
    }
}
