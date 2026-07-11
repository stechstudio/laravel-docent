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
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Models\DocentPageRevision;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Renderer\CodeBlockRenderer;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\TableOfContents;
use STS\Docent\Documents\Renderer\TocEntry;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
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
     * @return list<Navigation\NavigationItem|NavigationGroup>
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
        return $slug === '' ? route('docent.home') : route('docent.show', $slug);
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
        return (string) config('docent.theme.accent', '#6366f1');
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
     *     shadowed: bool, published: bool|null, hasUnpublishedChanges: bool|null, hidden: bool
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
        $page = DocentPage::on($this->databaseConnection())->where('slug', $slug)->first();

        if ($page !== null) {
            return $this->databaseDetail($page);
        }

        $source = $this->filesystem->find($slug);

        return $source === null ? null : $this->filesystemDetail($slug, $source);
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
     * Render a draft through the real pipeline (parse → HtmlRenderer + TOC) with
     * the given viewer's context, plus the inline reference checks — no
     * persistence. This is exactly what a reader would see if the draft were
     * published and they were this viewer.
     *
     * @param  array<string, mixed>  $frontMatter
     * @return array{html: string, toc: list<array<string, mixed>>, issues: list<array<string, mixed>>}
     */
    public function previewDraft(string $content, array $frontMatter, DocumentationContext $context): array
    {
        $document = $this->parser->parse($this->composeMarkdown($frontMatter, $content));

        return [
            'html' => $this->renderDocument($document, $context),
            'toc' => $this->tocToArray((new TableOfContents($this->registry, $context))->buildFor($document)),
            'issues' => $this->draftIssues('', $content, $frontMatter),
        ];
    }

    /**
     * The reference-check issues for a draft, as plain arrays ready for JSON —
     * unknown integrations, broken internal links, and missing includes for the
     * content as it would parse under the given slug.
     *
     * @param  array<string, mixed>  $frontMatter
     * @return list<array{severity: string, check: string, message: string, line: int|null}>
     */
    public function draftIssues(string $slug, string $content, array $frontMatter): array
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
            overrideContent: $this->composeMarkdown($frontMatter, $content),
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
     * the built-in icon names and every registered Gate ability.
     *
     * @return array<string, mixed>
     */
    public function pickerMeta(): array
    {
        return [
            ...$this->registry->describe(),
            'icons' => Icon::names(),
            'abilities' => array_keys(Gate::abilities()),
        ];
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
            'front_matter' => $frontMatter,
            'format' => $source->format,
            'store' => 'filesystem',
            'readonly' => true,
        ];
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
     * so a page is only parsed once per content revision. v1 ships only the
     * markdown parser; `DocumentSource::format` is the dispatch point a future
     * database/Tiptap repository plugs into.
     */
    private function document(DocumentSource $source): Document
    {
        $parser = match ($source->format) {
            DocumentSource::FORMAT_MARKDOWN => $this->parser,
        };

        return $this->cache->remember(
            'ast:'.$source->format.':'.$source->hash,
            fn (): Document => $parser->parse($source->rawContent),
        );
    }
}
