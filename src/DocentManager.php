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
use InvalidArgumentException;
use JsonException;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Models\DocentPageRevision;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Renderer\AgentMarkdownRenderer;
use STS\Docent\Documents\Renderer\CodeBlockRenderer;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\TableOfContents;
use STS\Docent\Documents\Renderer\TocEntry;
use STS\Docent\Documents\Serializer\AstToTiptap;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Navigation\NavigationItem;
use STS\Docent\Navigation\NavigationLink;
use STS\Docent\Navigation\NavigationSection;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\DocentCache;
use STS\Docent\Support\GrayPalette;
use STS\Docent\Support\Icon;
use STS\Docent\Support\InternalLink;
use STS\Docent\Support\RadiusScale;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\DocsChecker;
use STS\Docent\Validation\Issue;
use Symfony\Component\Yaml\Yaml;

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
        private readonly CodeBlockRenderer $codeBlockRenderer,
        private readonly FilesystemRepository $filesystem,
        private readonly DocumentationMode $mode,
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
        );
    }

    public function renderDocument(Document $document, DocumentationContext $context, string $baseDir = ''): string
    {
        $renderer = new HtmlRenderer(
            registry: $this->registry,
            context: $context,
            options: [
                'allow_html' => (bool) config('docent.content.allow_html', true),
                'debug' => (bool) config('app.debug', false),
                'base_dir' => $baseDir,
                'route_prefix' => (string) config('docent.route.prefix', 'docs'),
            ],
            includeResolver: fn (string $name): ?Document => $this->partialDocument($name),
            urlResolver: fn (string $slug): string => $this->url($slug),
            codeBlockRenderer: $this->codeBlockRenderer,
        );

        return $renderer->render($document);
    }

    /**
     * Render one page for agent-facing HTTP surfaces. The viewer fingerprint
     * isolates cached output for different navigation scopes and users.
     */
    public function agentMarkdown(Page $page, DocumentationContext $context): string
    {
        $key = implode(':', [
            'agent-page',
            $this->repository->directoryHash(),
            $this->viewerFingerprint($context),
            sha1($page->slug),
        ]);

        return $this->cache->remember($key, function () use ($page, $context): string {
            $renderer = new AgentMarkdownRenderer(
                registry: $this->registry,
                context: $context,
                baseDir: $page->baseDir(),
                routePrefix: (string) config('docent.route.prefix', 'docs'),
                includeResolver: fn (string $name): ?Document => $this->partialDocument($name),
                markdownUrlResolver: fn (string $slug): string => $this->markdownUrl($slug),
            );

            return $renderer->render($page->document(), $page->title(), $page->description());
        });
    }

    public function markdownUrl(string $slug): string
    {
        return route('docent.show', ['slug' => ($slug === '' ? 'index' : $slug).'.md']);
    }

    public function llmsUrl(bool $full = false): string
    {
        return route($full ? 'docent.llms-full' : 'docent.llms');
    }

    public function discoveryLinkHeader(): string
    {
        $index = parse_url($this->llmsUrl(), PHP_URL_PATH) ?: '/llms.txt';
        $full = parse_url($this->llmsUrl(true), PHP_URL_PATH) ?: '/llms-full.txt';

        return '<'.$index.'>; rel="llms-txt", <'.$full.'>; rel="llms-full-txt"';
    }

    public function llmsText(DocumentationContext $context): string
    {
        $navigationSections = $this->navigationSections($context);
        $key = 'llms:'.$this->repository->directoryHash().':'.$this->viewerFingerprint($context);

        return $this->cache->remember($key, function () use ($navigationSections): string {
            $sections = [];

            // Within each section, ungrouped pages sit under the section's own
            // heading and every top-level group keeps its own — a sectionless
            // site reads exactly as it did before sections existed.
            foreach ($navigationSections as $section) {
                $root = array_values(array_filter(
                    $section->navigation,
                    static fn (object $node): bool => $node instanceof NavigationItem,
                ));

                if ($root !== []) {
                    $sections[] = $this->llmsSection($section->label, $root);
                }

                foreach ($section->navigation as $node) {
                    if ($node instanceof NavigationGroup) {
                        $sections[] = $this->llmsSection($node->label, $this->flattenNavigation([$node]));
                    }
                }
            }

            return '# '.$this->siteName()."\n\n> ".$this->siteDescription()."\n"
                .($sections === [] ? '' : "\n".implode("\n\n", $sections)."\n");
        });
    }

    public function llmsFullText(DocumentationContext $context): string
    {
        $navigationSections = $this->navigationSections($context);
        $key = 'llms-full:'.$this->repository->directoryHash().':'.$this->viewerFingerprint($context);

        return $this->cache->remember($key, function () use ($navigationSections, $context): string {
            $pages = [];

            foreach ($navigationSections as $section) {
                foreach ($this->flattenNavigation($section->navigation) as $item) {
                    if ($item->searchExcluded) {
                        continue;
                    }

                    $page = $this->page($item->slug);

                    if ($page !== null && $page->authorize($context)) {
                        $pages[] = trim($this->agentMarkdown($page, $context));
                    }
                }
            }

            return $pages === [] ? '' : implode("\n\n---\n\n", $pages)."\n";
        });
    }

    public function siteDescription(): string
    {
        $description = config('docent.description');

        return is_string($description) && trim($description) !== ''
            ? trim($description)
            : 'Application guides and help documentation for '.$this->siteName().'.';
    }

    /** @return array<string, mixed> */
    public function widgetConfig(): array
    {
        $mode = config('docent.widget.mode') === 'push' ? 'push' : 'overlay';
        $position = config('docent.widget.position') === 'left' ? 'left' : 'right';
        $launcher = config('docent.widget.launcher') === 'none' ? 'none' : 'button';
        $offset = max(0, (int) config('docent.widget.offset', 24));
        $icon = (string) config('docent.widget.icon', 'book-open');

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
            'suggestionsUrl' => route('docent.widget.suggestions'),
            'page' => Route::currentRouteName(),
            'assetUrl' => $this->asset('docent-widget.js'),
            'mode' => $mode,
            'position' => $position,
            'offset' => $offset,
            'launcher' => $launcher,
            'preload' => (bool) config('docent.widget.preload', false),
            'icon' => $iconMarkup,
            'accent' => $this->accent(),
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

            if ($page === null || ! $page->authorize($context)) {
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

    /** @param list<NavigationItem|NavigationGroup> $nodes
     * @return list<NavigationItem>
     */
    private function flattenNavigation(array $nodes): array
    {
        $items = [];

        foreach ($nodes as $node) {
            if ($node instanceof NavigationItem) {
                $items[] = $node;
            } else {
                array_push($items, ...$node->items, ...$this->flattenNavigation($node->groups));
            }
        }

        return $items;
    }

    /** @param list<NavigationItem> $items */
    private function llmsSection(string $label, array $items): string
    {
        $lines = ['## '.$label];

        foreach ($items as $item) {
            $line = '- ['.$item->title.']('.$this->markdownUrl($item->slug).')';
            $description = preg_replace('/\s+/', ' ', trim((string) $item->description));
            $lines[] = $line.($description === '' ? '' : ': '.$description);
        }

        return implode("\n\n", $lines);
    }

    public function viewerFingerprint(DocumentationContext $context): string
    {
        $slugs = array_map(
            static fn (NavigationItem $item): string => $item->slug,
            $this->flattenNavigation($this->navigation($context)),
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
        return $slug === '' ? route('docent.home') : route('docent.show', $slug);
    }

    public function widgetUrl(string $slug = ''): string
    {
        return $slug === ''
            ? route('docent.widget.home')
            : route('docent.widget.show', ['slug' => $slug]);
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
        $target = InternalLink::resolve($destination, $baseDir, (string) config('docent.route.prefix', 'docs'));

        return $target === null ? $destination : $this->url($target['slug']).$target['suffix'];
    }

    public function siteName(): string
    {
        return (string) config('docent.name');
    }

    /**
     * The accent colour driving the whole UI, from `docent.theme.accent`.
     */
    public function accent(): string
    {
        return (string) config('docent.theme.accent', '#0284c7');
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
        $value = config('docent.theme.'.$key);

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

        return route('docent.asset', ['file' => $file]).'?v='.$this->assetVersion($this->assetPath($file));
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

    /**
     * The admin page tree: every database page (drafts included — the whole
     * point of the panel) and every filesystem page, as a flat list the UI can
     * group by directory. `shadowed` marks the drift the ROADMAP insists we
     * surface: a database page overriding a like-named file (true on both the
     * database entry doing the shadowing and the file entry being shadowed).
     * Underscored slugs (`_partials/…`) are excluded, mirroring the reader
     * enumeration. Filesystem pages are read-only here; their `published` /
     * `hasUnpublishedChanges` are null.
     *
     * @return list<array{
     *     slug: string, title: string, group: string, store: string,
     *     shadowed: bool, published: bool|null, hasUnpublishedChanges: bool|null, hidden: bool, locked: bool
     * }>
     */
    public function adminTree(): array
    {
        $files = [];

        foreach ($this->filesystem->all() as $reference) {
            $files[$reference->slug] = $reference;
        }

        $pages = DocentPage::on($this->databaseConnection())->get();

        $dbSlugs = [];

        foreach ($pages as $page) {
            $dbSlugs[$page->slug] = true;
        }

        $entries = [];

        foreach ($pages as $page) {
            if ($this->isUnderscored($page->slug)) {
                continue;
            }

            $frontMatter = new FrontMatter($page->front_matter ?? []);

            $entries[] = [
                'slug' => $page->slug,
                'title' => $page->title,
                'group' => $this->baseDirOf($page->slug),
                'store' => 'database',
                'shadowed' => isset($files[$page->slug]),
                'published' => $page->isPublished(),
                'hasUnpublishedChanges' => $page->hasUnpublishedChanges(),
                'hidden' => $frontMatter->hidden(),
                'locked' => ($files[$page->slug] ?? null)?->locked ?? false,
            ];
        }

        foreach ($files as $slug => $reference) {
            $entries[] = [
                'slug' => $slug,
                'title' => $reference->title,
                'group' => $reference->directory,
                'store' => 'filesystem',
                'shadowed' => isset($dbSlugs[$slug]),
                'published' => null,
                'hasUnpublishedChanges' => null,
                'hidden' => $reference->hidden,
                'locked' => $reference->locked,
            ];
        }

        return $entries;
    }

    /**
     * The editor payload for a slug: the database draft (its current, possibly
     * unpublished content) when one exists, otherwise the read-only filesystem
     * page. Null when neither store has the slug (404).
     *
     * @return array<string, mixed>|null
     */
    public function adminDetail(string $slug): ?array
    {
        if ($this->filesystem->pageLocked($slug)) {
            $source = $this->filesystem->find($slug);

            return $source === null ? null : $this->filesystemDetail($slug, $source);
        }

        $page = DocentPage::on($this->databaseConnection())->where('slug', $slug)->first();

        if ($page !== null) {
            return $this->databaseDetail($page);
        }

        $source = $this->filesystem->find($slug);

        return $source === null ? null : $this->filesystemDetail($slug, $source);
    }

    public function filesystemSlugLocked(string $slug): bool
    {
        if (str_starts_with($slug, '_partials/')) {
            return $this->filesystem->partialLocked(substr($slug, strlen('_partials/')));
        }

        return $this->filesystem->pageLocked($slug);
    }

    /**
     * Every directory that currently holds pages — across the filesystem and the
     * database, and including each nested ancestor as its own entry — with its
     * effective group metadata. `source` records where the effective values come
     * from: 'database' (a `_groups/` override row exists), 'file' (a `_group.yml`
     * provides it), or null (pure defaults). `''` (root) is never a group.
     * Effective label/order/icon reflect the composite cascade (database wins).
     *
     * @return list<array{directory: string, label: string, order: int|null, icon: string|null, source: string|null}>
     */
    public function adminGroups(): array
    {
        $pages = DocentPage::on($this->databaseConnection())->get();

        $directories = [];
        $dbGroups = [];

        foreach ($pages as $page) {
            if (str_starts_with($page->slug, '_groups/')) {
                $dbGroups[substr($page->slug, strlen('_groups/'))] = true;

                continue;
            }

            if ($this->isUnderscored($page->slug)) {
                continue;
            }

            $this->collectDirectories($directories, $this->baseDirOf($page->slug));
        }

        foreach ($this->filesystem->all() as $reference) {
            $this->collectDirectories($directories, $reference->directory);
        }

        $groups = [];

        foreach (array_keys($directories) as $directory) {
            $meta = $this->repository->groupMeta($directory) ?? [];
            $order = $meta['order'] ?? null;
            $label = $meta['label'] ?? null;
            $icon = $meta['icon'] ?? null;

            $groups[] = [
                'directory' => $directory,
                'label' => is_scalar($label) && (string) $label !== '' ? (string) $label : Str::headline(Str::afterLast($directory, '/')),
                'order' => is_numeric($order) ? (int) $order : null,
                'icon' => is_string($icon) && $icon !== '' ? $icon : null,
                'source' => isset($dbGroups[$directory])
                    ? 'database'
                    : ($this->filesystem->groupMeta($directory) !== null ? 'file' : null),
            ];
        }

        usort($groups, static fn (array $a, array $b): int => [$a['order'] ?? PHP_INT_MAX, $a['label']] <=> [$b['order'] ?? PHP_INT_MAX, $b['label']]);

        return $groups;
    }

    /**
     * Store (or replace) a directory's group metadata as a reserved, never-published
     * `_groups/{directory}` row — taking effect immediately, no publish step. The
     * database override wins over any `_group.yml` of the same directory.
     *
     * @param  array<string, mixed>  $meta
     */
    public function updateGroupMeta(string $directory, array $meta, ?int $authorId): void
    {
        DocentPage::write('_groups/'.$directory, '', $meta, $authorId);
    }

    /**
     * Discard a directory's database group override, restoring whatever the
     * filesystem `_group.yml` (or defaults) provide. False when none existed.
     */
    public function removeGroupMeta(string $directory): bool
    {
        return (bool) DocentPage::on($this->databaseConnection())
            ->where('slug', '_groups/'.$directory)
            ->first()?->delete();
    }

    /**
     * Copy a filesystem page into a new database draft, so it can be edited
     * without touching the repository. Returns null when no such file exists;
     * the caller rejects an already-overridden slug (409) before calling.
     */
    public function overrideFromFilesystem(string $slug, ?int $authorId): ?DocentPage
    {
        $source = $this->filesystem->find($slug);

        if ($source === null) {
            return null;
        }

        [$frontMatter, $content] = $this->splitFrontMatter($source->rawContent);

        return DocentPage::write($slug, $content, $frontMatter, $authorId);
    }

    /**
     * Parse an admin draft into a Document ready for preview, checks, or export.
     * A markdown draft is its front matter composed back over the body and
     * parsed as usual; a Tiptap draft is JSON parsed by {@see TiptapDocumentParser}
     * with the form's front matter applied over the (metadata-free) body — the
     * same override the database repository performs for published Tiptap pages.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    public function draftDocument(string $format, string $content, array $frontMatter): Document
    {
        return $format === DocumentSource::FORMAT_TIPTAP
            ? $this->withFrontMatter((new TiptapDocumentParser)->parse($content), $frontMatter)
            : $this->parser->parse($this->composeMarkdown($frontMatter, $content));
    }

    /**
     * Validate that a Tiptap document (as decoded from the editor) parses into
     * the AST, returning the parser's message on failure or null on success.
     * The store/preview controllers turn a non-null result into a 422 — this is
     * the one place we validate an untrusted editor payload at the boundary.
     *
     * @param  array<string, mixed>  $content
     */
    public function tiptapError(array $content): ?string
    {
        try {
            (new TiptapDocumentParser)->parse(json_encode($content, JSON_THROW_ON_ERROR));

            return null;
        } catch (JsonException|InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /**
     * Export any page — file or database, markdown or Tiptap — to normalized
     * markdown with a front matter block, the pivot always being the Docent AST.
     * Powers the admin "View markdown" / file-export action. Null when no such
     * page exists.
     */
    public function exportMarkdown(string $slug): ?string
    {
        $page = $this->filesystemSlugLocked($slug)
            ? null
            : DocentPage::on($this->databaseConnection())->where('slug', $slug)->first();

        if ($page !== null) {
            $frontMatter = $page->front_matter ?? ['title' => $page->title];
            $document = $this->draftDocument($page->format, $page->content, $frontMatter);

            return (new MarkdownExporter)->withFrontMatter($frontMatter)->export($document);
        }

        $source = $this->filesystem->find($slug);

        if ($source === null) {
            return null;
        }

        [$frontMatter, $content] = $this->splitFrontMatter($source->rawContent);
        $document = $this->draftDocument($source->format, $content, $frontMatter);

        return (new MarkdownExporter)->withFrontMatter($frontMatter)->export($document);
    }

    /**
     * Render a draft through the real pipeline (HtmlRenderer + TOC) with the
     * given viewer's context, plus the inline reference checks — no persistence.
     * This is exactly what a reader would see if the draft were published and
     * they were this viewer.
     *
     * @return array{html: string, toc: list<array<string, mixed>>, issues: list<array<string, mixed>>}
     */
    public function previewDraft(Document $document, DocumentationContext $context, string $slug = ''): array
    {
        return [
            'html' => $this->renderDocument($document, $context),
            'toc' => $this->tocToArray((new TableOfContents($this->registry, $context))->buildFor($document)),
            'issues' => $this->draftIssues($slug, $document),
        ];
    }

    /**
     * The reference-check issues for a pre-parsed draft, as plain arrays ready
     * for JSON — unknown integrations, broken internal links, and missing
     * includes for the document as it would resolve under the given slug. Format
     * agnostic: the draft is already an AST, so markdown and Tiptap drafts run
     * the identical checks.
     *
     * @return list<array{severity: string, check: string, message: string, line: int|null}>
     */
    public function draftIssues(string $slug, Document $document): array
    {
        $context = new CheckContext(
            repository: $this->repository,
            parser: $this->parser,
            registry: $this->registry,
            docsPath: (string) (config('docent.filesystem.path') ?? resource_path('docs')),
            publicPath: public_path(),
            routePrefix: (string) config('docent.route.prefix', 'docs'),
            routeExists: static fn (string $name): bool => Route::has($name),
            abilityExists: static fn (string $ability): bool => Gate::has($ability),
            overrideSlug: $slug,
            overrideDocument: $document,
        );

        return array_map(
            static fn (Issue $issue): array => [
                'severity' => $issue->severity->value,
                'check' => $issue->check,
                'message' => $issue->message,
                'line' => $issue->line,
            ],
            DocsChecker::references()->run($context),
        );
    }

    /**
     * Registry metadata for the editor's node/reference pickers: conditions,
     * values, links, components, and audiences (name/label/description), plus
     * the built-in icon names and every registered Gate ability. Abilities
     * carry a humanized `label` alongside the technical `name` so every picker
     * shows "View reports", not `reports.view` — the stored value stays the
     * technical name.
     *
     * @return array<string, mixed>
     */
    public function pickerMeta(): array
    {
        return [
            ...$this->registry->describe(),
            'icons' => Icon::names(),
            'abilities' => array_map(
                fn (string $ability): array => ['name' => $ability, 'label' => $this->abilityLabel($ability)],
                array_keys(Gate::abilities()),
            ),
        ];
    }

    /**
     * A human label for a technical gate ability: split on separators and
     * camelCase, and lead with the verb when the name ends in one —
     * `reports.view` → "View reports", `manage-billing` → "Manage billing".
     */
    private function abilityLabel(string $ability): string
    {
        $words = array_map(
            strtolower(...),
            preg_split('/[.\-_:\s]+|(?=[A-Z])/', $ability, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );

        $verbs = ['view', 'see', 'read', 'access', 'manage', 'edit', 'update', 'create', 'delete', 'export', 'download'];

        if (count($words) > 1 && in_array(end($words), $verbs, true)) {
            array_unshift($words, array_pop($words));
        }

        return Str::ucfirst(implode(' ', $words));
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseDetail(DocentPage $page): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'content_tiptap' => $this->tiptapFor($page->content, $page->format),
            'front_matter' => $page->front_matter ?? [],
            'format' => $page->format,
            'published' => $page->isPublished(),
            'hasUnpublishedChanges' => $page->hasUnpublishedChanges(),
            'revisions' => $page->revisions()->latest('id')->limit(20)->get()->map(
                static fn (DocentPageRevision $revision): array => [
                    'id' => $revision->id,
                    'created_at' => $revision->created_at,
                    'created_by' => $revision->created_by,
                ],
            )->all(),
            'store' => 'database',
            'locked' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filesystemDetail(string $slug, DocumentSource $source): array
    {
        [$frontMatter, $content] = $this->splitFrontMatter($source->rawContent);

        return [
            'slug' => $slug,
            'title' => (new FrontMatter($frontMatter))->title() ?? ($slug === '' ? 'Home' : Str::headline(Str::afterLast($slug, '/'))),
            'content' => $content,
            'content_tiptap' => $this->tiptapFor($content, $source->format),
            'front_matter' => $frontMatter,
            'format' => $source->format,
            'store' => 'filesystem',
            'readonly' => true,
            'locked' => $this->filesystemSlugLocked($slug),
        ];
    }

    /**
     * The editor's Tiptap view of a page's body. A Tiptap page is decoded
     * straight from its stored JSON; a markdown page is parsed and serialized so
     * the visual editor can open any page. (Stored JSON is our own data, so a
     * decode failure is a bug and throws.)
     *
     * @return array<string, mixed>
     */
    private function tiptapFor(string $content, string $format): array
    {
        if ($format === DocumentSource::FORMAT_TIPTAP) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        }

        return (new AstToTiptap)->convert($this->parser->parse($content));
    }

    /**
     * Split a raw markdown file into its parsed front matter and its body. Repo
     * files are app code (reviewed in PRs), so a malformed YAML block is a bug
     * that should surface loudly rather than be swallowed.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $raw): array
    {
        if (preg_match('/^---\R(.*?)\R---\s*(?:\R|$)/s', $raw, $matches) !== 1) {
            return [[], $raw];
        }

        $data = Yaml::parse($matches[1]);

        return [is_array($data) ? $data : [], substr($raw, strlen($matches[0]))];
    }

    /**
     * Compose a page's front matter (when any) and body back into a single
     * markdown document, the shape the parser and reference checks expect.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    private function composeMarkdown(array $frontMatter, string $content): string
    {
        if ($frontMatter === []) {
            return $content;
        }

        return '---'."\n".Yaml::dump($frontMatter).'---'."\n\n".$content;
    }

    /**
     * @param  list<TocEntry>  $entries
     * @return list<array{title: string, slug: string, level: int, children: array<int, mixed>}>
     */
    private function tocToArray(array $entries): array
    {
        return array_map(
            fn (TocEntry $entry): array => [
                'title' => $entry->title,
                'slug' => $entry->slug,
                'level' => $entry->level,
                'children' => $this->tocToArray($entry->children),
            ],
            $entries,
        );
    }

    private function baseDirOf(string $slug): string
    {
        return str_contains($slug, '/') ? substr($slug, 0, (int) strrpos($slug, '/')) : '';
    }

    /**
     * Record a directory and every ancestor prefix as its own group entry —
     * `guides/advanced` contributes both `guides` and `guides/advanced`, mirroring
     * the group node NavigationBuilder creates for each path segment.
     *
     * @param  array<string, true>  $set
     */
    private function collectDirectories(array &$set, string $directory): void
    {
        if ($directory === '') {
            return;
        }

        $accumulated = '';

        foreach (explode('/', $directory) as $segment) {
            $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;
            $set[$accumulated] = true;
        }
    }

    private function isUnderscored(string $slug): bool
    {
        foreach (explode('/', $slug) as $segment) {
            if (str_starts_with($segment, '_')) {
                return true;
            }
        }

        return false;
    }

    private function databaseConnection(): ?string
    {
        $connection = config('docent.database.connection');

        return $connection === null ? null : (string) $connection;
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

        return $source->frontMatter === null
            ? $document
            : $this->withFrontMatter($document, $source->frontMatter);
    }

    /**
     * A copy of a parsed document with its front matter replaced — the seam that
     * lets a Tiptap source's out-of-band metadata override the empty front
     * matter the JSON parser produces.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    private function withFrontMatter(Document $document, array $frontMatter): Document
    {
        $replacement = new Document(new FrontMatter($frontMatter), $document->line);
        $replacement->setChildren($document->children);

        return $replacement;
    }
}
