# Docent — v1 Architecture Design

This is the authoritative build spec for v1. It condenses `handoff.md` into concrete engineering decisions.
Executors: follow this document. If something is ambiguous, prefer the handoff document's principles
(§27) and record any judgment calls in DECISIONS.md.

## Identity

- Composer: `stechstudio/laravel-docent`
- Namespace: `STS\Docent`
- Facade: `STS\Docent\Facades\Docent`
- Config: `config/docent.php`
- Commands: `docent:install`, `docent:check`, `docent:clear`
- Default route prefix: `/docs`
- Targets: PHP ^8.3, Laravel ^12 | ^13

## Core principle

The runtime is the product. Markdown is an input format; the **Docent AST** is the canonical
document model. Nothing renders Markdown directly to HTML. Pipeline:

```
DocumentSource → Parser → Docent AST → (cache) → context-aware Renderers → HTML / search text / TOC
```

Fast static work, late dynamic work: parsing/AST is globally cacheable; authorization and dynamic
values are resolved per-request at render time. Personalized HTML is never globally cached in v1.

## Directory layout (package `src/`)

```
src/
├── DocentServiceProvider.php
├── DocentManager.php              // facade root: registry + high-level API
├── Facades/Docent.php
├── Console/                       // InstallCommand, CheckCommand, ClearCommand
├── Content/
│   ├── DocumentSource.php         // slug, raw content, hash, path, lastModified
│   ├── PageReference.php          // lightweight: slug, title, front matter (for nav/enumeration)
│   └── Repositories/
│       ├── DocumentationRepository.php   // interface (internal/experimental in v1)
│       └── FilesystemRepository.php
├── Documents/
│   ├── Document.php               // AST root + front matter accessor
│   ├── FrontMatter.php
│   ├── Ast/                       // node classes (below)
│   ├── Parser/
│   │   ├── DocumentParser.php     // interface
│   │   ├── MarkdownDocumentParser.php
│   │   └── Markdown/              // commonmark extensions + AST converter
│   └── Renderer/
│       ├── HtmlRenderer.php
│       ├── SearchTextRenderer.php
│       ├── PlainTextRenderer.php
│       └── TableOfContents.php    // builder + value objects
├── Runtime/
│   ├── DocumentationContext.php
│   ├── IntegrationRegistry.php    // conditions, values, links, components, audiences
│   ├── Registered/                // RegisteredCondition, RegisteredValue, ... (name, resolver, label, description)
│   └── Contracts/                 // DocumentationComponent interface etc.
├── Navigation/
│   ├── NavigationBuilder.php      // filesystem → tree, then authorization filter
│   ├── NavigationGroup.php
│   └── NavigationItem.php
├── Search/
│   ├── SearchIndexer.php          // AST → SearchRecord[]
│   ├── SearchRecord.php
│   ├── SearchEngine.php           // query + authorization filter + snippets
│   └── SearchResult.php
├── Http/
│   ├── Controllers/ (PageController, SearchController)
│   └── Middleware/ (if needed)
├── Validation/
│   ├── DocsChecker.php            // orchestrates checks
│   └── Checks/                    // one class per check type
├── Testing/
│   └── InteractsWithDocs.php      // testing helpers trait
└── Support/
```

## AST

Plain PHP classes in `STS\Docent\Documents\Ast`. All nodes extend abstract `Node`.
Container nodes hold `Node[] $children`. Nodes are pure data — **no rendering logic on nodes**.
Must be safely `serialize()`-able (for caching). Keep constructors promoted-property style.

Block nodes:
- `Document` (root)
- `Heading` (level, plus computed `slug` for anchors)
- `Paragraph`
- `BlockQuote`
- `BulletList`, `OrderedList` (start), `ListItem` (checked: ?bool for task lists)
- `Table`, `TableSection` (head/body), `TableRow`, `TableCell` (header bool, align)
- `CodeBlock` (language, code, filename? via `info` string)
- `ThematicBreak`
- `HtmlBlock` (raw html — rendered only when config allows; stripped otherwise)
- `Callout` (type: note|tip|info|warning|danger, optional title) — authored as `:::note` etc.
- `AuthorizationBlock` (mode: can|cannot, ability, arguments: string[])
- `ConditionBlock` (condition name, negated bool, arguments)
- `AudienceBlock` (audience name)
- `IncludeNode` (name) — resolved at render/analysis time from `_partials/`
- `ComponentNode` (name, attributes: array<string,string>) — authored as `<docs-component name="x" :arg="y" />`

Inline nodes:
- `Text` (literal)
- `Emphasis`, `Strong`, `Strikethrough`
- `InlineCode`
- `Link` (destination, title) — destination may be a `DocLink` value (below)
- `Image` (url, alt, title)
- `HardBreak`, `SoftBreak`
- `HtmlInline` (policy-controlled like HtmlBlock)
- `DynamicValue` (key, arguments) — authored `{{ value:account.plan_name }}`
- `AppLink` (kind: link|route, key, parameters) — authored `{{ link:billing.settings }}` or `{{ route:dashboard }}`; valid anywhere, most commonly as a link destination

Every node carries optional `?int $line` (source line) for error reporting in `docent:check`.

## Authoring syntax

Markdown (CommonMark + GFM tables/strikethrough/task lists/autolinks) with YAML front matter.

Front matter keys (v1): `title`, `description`, `authorize` (gate/ability string),
`audience` (audience name), `order` (int, nav sort), `hidden` (bool, exclude from nav),
`search: { exclude: bool }`, `redirect` (slug → this page later; keep parsed but ok to defer behavior).

Container directives (fenced, `:::name key="value"`, closed by `:::`, nestable — parser must
track nesting depth):

```markdown
:::can ability="billing.manage"
:::cannot ability="billing.manage"
:::when condition="advanced-exports"        (also :::unless)
:::audience name="billing-admin"
:::include name="permissions-note"          (self-contained; no body — closing ::: optional)
:::note  /  :::tip  /  :::info  /  :::warning  /  :::danger   (optional title="...")
```

Inline tokens: `{{ value:key }}`, `{{ link:key }}`, `{{ route:name }}` (also with args:
`{{ value:key arg1 arg2 }}` — space-separated args after the key; keep simple).
Also work inside markdown link destinations: `[Billing]({{ link:billing.settings }})`.

Components: `<docs-component name="billing-usage" plan="pro" />` (self-closing HTML-style tag,
parsed into `ComponentNode`, never emitted as raw HTML).

Internal doc links: relative markdown links like `[Setup](/docs/getting-started/setup)` or
slug-relative `[Setup](getting-started/setup)` — HtmlRenderer resolves slug-style destinations
against the docs route.

## Parser

`MarkdownDocumentParser` built on `league/commonmark`:
- `CommonMarkCoreExtension` + `FrontMatterExtension` (symfony/yaml) + GFM bits (table, strikethrough, task list, autolink)
- Custom block-start parsers for `:::` container directives
- Custom inline parser for `{{ kind:key ... }}` tokens
- Custom handling of `<docs-component ... />`
- Final stage: walk the commonmark AST and convert to the Docent AST. The commonmark AST never
  leaves the parser.
- Heading slugs generated during conversion (github-style slugger, de-duplicated per document).

## Runtime

```php
final class DocumentationContext
{
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly ?Request $request,
        public readonly array $parameters = [],
        public readonly ?string $audience = null,   // preview override
    ) {}

    public function can(string $ability, array $arguments = []): bool;   // Gate::forUser
}
```

`IntegrationRegistry` (singleton behind `Docent` facade):

```php
Docent::condition('advanced-exports', fn (DocumentationContext $ctx): bool => ..., label: '...', description: '...');
Docent::value('account.plan_name', fn (DocumentationContext $ctx): string => ...);
Docent::link('billing.settings', fn (DocumentationContext $ctx): string => route('billing.settings'));
Docent::component('billing-usage', BillingUsageComponent::class);   // class implements DocumentationComponent
Docent::audience('billing-admin', fn (DocumentationContext $ctx): bool => ...);
```

- Resolvers may be closures or invokable/contract-implementing classes (resolved via container).
- All registrations accept optional `label`/`description` metadata (future editor + docent:check output).
- Unknown identifiers at render time: render nothing for blocks, an inline “⚠ missing” HTML comment
  marker in local/debug, log a warning; `docent:check` is the loud path.

`DocumentationComponent` contract:

```php
interface DocumentationComponent
{
    public function render(DocumentationContext $context, array $attributes): Htmlable|string;
}
```

Components can return Blade views. v1 components are server-rendered only.

## Rendering rules

`HtmlRenderer` walks the AST with a `DocumentationContext`:
- `AuthorizationBlock` → `$context->can()` (cannot = negation); children rendered or dropped.
- `ConditionBlock` → registry condition; `AudienceBlock` → registry audience.
- `DynamicValue` → resolved, **always HTML-escaped**.
- `AppLink` → resolved URL (registered link resolver, or `route()` for `route:` kind).
- `IncludeNode` → parse + render the partial (cycle guard: track include stack, max depth 10).
- `ComponentNode` → component render output (trusted HTML — components are app code).
- `HtmlBlock`/`HtmlInline` → emitted only if `config('docent.content.allow_html')` (default **true**
  for repository-authored files; they're app code in the repo — but sanitize nothing, just a config gate).
- Code blocks → server-side syntax highlighting (see UI section), with graceful plain `<pre><code>` fallback.
- All text escaped via `e()`; hrefs escaped; heading anchors `<h2 id="slug">`.

`SearchTextRenderer`: plain text for indexing. **Skips** AuthorizationBlock/ConditionBlock/
AudienceBlock content entirely (leak-safe default), skips DynamicValue (emits nothing),
skips components/includes(? includes: resolve them — they're static content — but conditional
blocks inside resolved includes are still skipped).

`PlainTextRenderer`: like SearchTextRenderer but context-aware (renders what the given context
may see) — used for snippets and testing helpers.

## Page resolution + authorization

- `FilesystemRepository` scans `config('docent.filesystem.path')` (default `resource_path('docs')`).
- Slug = path minus extension; `index.md` maps to the directory slug; root `index.md` = docs home.
- Front matter `authorize` → `Gate` check per request; denied → response per
  `config('docent.authorization.denied_response')` (default 404). `audience` front matter also
  gates the whole page.
- Hidden/unauthorized pages are removed from navigation, search, and prev/next.

## Navigation

- Directories = groups. Group label = title-cased directory name, overridable via `_group.yml`
  (`label`, `order`, `icon`) in the directory.
- Pages ordered by front matter `order`, then title. Groups ordered by `_group.yml` order, then name.
- `_partials/` and files starting with `_` are never pages.
- Navigation skeleton (pre-authorization) cached globally; authorization filter applied per request.
- Prev/next computed from the flattened, authorization-filtered nav.

## Caching

- Parsed `Document` (AST) cached in the configured cache store, key `docent:ast:{slug}:{content-hash}`.
- Navigation skeleton cached with a directory content hash.
- Search index cached similarly.
- `docent:clear` flushes; content-hash keys make stale entries harmless.
- No personalized-HTML caching in v1.

## Search

- `SearchIndexer` builds `SearchRecord` per page: slug, title, description, headings[],
  body (SearchTextRenderer output), authorize/audience metadata. Stored via cache store.
- `SearchEngine::search(query, context)`: tokenized matching with weights
  (title 5×, headings 3×, description 2×, body 1×; prefix matching on tokens),
  authorization-filter pages first, snippets generated by highlighting matched terms
  in the (already leak-safe) indexed body text.
- Endpoint: `GET {prefix}/_search?q=…` returns JSON `{results: [{slug, url, title, group, snippet(html <mark>), headings}]}`.
  Same middleware as page routes.

## HTTP

- Routes registered by the service provider from config: prefix, domain, middleware
  (default `['web']`; docs suggest `['web','auth']` in comments).
- `GET {prefix}` → home page (index.md); `GET {prefix}/{slug}` (wildcard, validated); `GET {prefix}/_search`.
- Route names: `docent.show`, `docent.search`.

## docent:check

Checks (each a class in `Validation/Checks/`, reporting `Issue` objects with severity
error|warning, page slug, line):
- Front matter: invalid YAML, missing title, duplicate slug (case-insensitive collisions)
- Broken internal links (slug-style link destinations that match no page)
- Unknown conditions / values / links / components / audiences (vs registry)
- `route:` links referencing nonexistent named routes
- Unknown gate/ability names in `authorize` + can/cannot **(warning only — Gate may define at runtime)**
- Missing include partials + include cycles
- Missing local images
- Heading hierarchy jumps (h2 → h4) — warning
- `--strict` exits non-zero on warnings too; errors always exit non-zero.

## Testing helpers

`STS\Docent\Testing\InteractsWithDocs` trait:

```php
$this->docs()->page('billing/payment-methods')->as($user)
    ->assertVisible()->assertSee('Update your payment method')->assertDontSee('Internal');
$this->docs()->search('payroll', as: $user)->assertMissing('Payroll Reports')->assertSees('...');
```

Implemented against the renderer directly (not HTTP) for speed, plus `assertVisible/assertForbidden`
consulting page authorization.

## UI (Milestone 8 — detailed brief lives in the M8 executor prompt)

Mintlify-caliber. Blade views published under `vendor/docent`, precompiled Tailwind CSS + one small
vanilla-JS/Alpine bundle shipped in `resources/dist`, served via `AboutCommand`-style asset publish
(`php artisan vendor:publish --tag=docent-assets`) AND a fallback asset route so `/docs` works with
zero publish steps. Server-side syntax highlighting with dual light/dark theme output.
Layout: top bar (logo, search trigger, dark-mode toggle) / left sidebar (groups) / prose center /
right rail ("On this page", scroll-spy). ⌘K command palette search. Keyboard navigable. Responsive.

## Config (`config/docent.php`)

```php
return [
    'name' => env('DOCENT_NAME', config('app.name').' Docs'),
    'route' => ['prefix' => 'docs', 'domain' => null, 'middleware' => ['web']],
    'filesystem' => ['path' => null /* resource_path('docs') resolved in provider */],
    'authorization' => ['denied_response' => 404],
    'content' => ['allow_html' => true],
    'search' => ['enabled' => true],
    'cache' => ['store' => null, 'prefix' => 'docent'],
    'theme' => [ /* logo, colors — M8 defines */ ],
];
```

## Testing conventions

- Pest. `tests/Unit` (pure PHP, no Laravel) for AST/parser/renderer; `tests/Feature`
  (Testbench TestCase) for repository/HTTP/search/commands.
- Fixtures under `tests/fixtures/docs/…` as a miniature docs tree.
- Workbench app (`workbench/`) is the demo: sample docs in `workbench/resources/docs`,
  demo users + gates, registered example integrations. It is both the browser-test target
  and the eventual marketing screenshot source.

## Code style

- PHP 8.3+: promoted constructors, readonly where sensible, enums for closed sets
  (CalloutType, AuthorizationMode, Severity, etc.), `match` over if-chains.
- Follow the user's Laravel preferences: no defensive try/catch around our own code;
  model/manager affordances over service sprawl; no speculative config or columns.
- Pint for formatting (`vendor/bin/pint`).

## Tiptap schema contract (Phase C)

The single contract shared by the PHP bridge and the JS editor. The schema is CLOSED and exactly
co-extensive with the markdown dialect: every node has a canonical markdown spelling, so
AST → markdown is a total function. The Docent AST is always the pivot — never convert
Tiptap ↔ markdown directly.

Round-trip promise: SEMANTIC, not byte-level. Exports are normalized markdown (ATX headings,
`**`/`*` emphasis, `-` bullets, fenced code with backticks, directives with minimal fences,
one blank line between blocks). Export must be a fixpoint: export(parse(export(x))) === export(x).
No interactive source mode in the editor; read-only "View markdown" only.

### Document shape
Standard ProseMirror: `{"type":"doc","content":[...]}`. Stored verbatim as the page's `content`
(JSON string) with `format: 'tiptap'`. Front matter stays in its column — never inside the doc.

### Standard nodes (Tiptap core, AST mapping)
paragraph→Paragraph · heading{level}→Heading (slugs computed at parse) · text(+marks)→Text/
Emphasis/Strong/Strikethrough/InlineCode wrappers · bulletList/orderedList{start}/listItem
{checked?}→BulletList/OrderedList/ListItem (checked non-null = task item) · blockquote→BlockQuote ·
codeBlock{language, title?}→CodeBlock · horizontalRule→ThematicBreak · image{src,alt,title}→Image ·
table/tableRow/tableHeader/tableCell→Table tree · hardBreak→HardBreak.
Marks: bold, italic, strike, code, link{href} (href may be an internal slug — preserved verbatim,
resolved at render).

### Docent nodes (custom; names are the wire format — do not rename)
- `docsGate` attrs `{mode: 'can'|'cannot', ability, arguments: []}` block container → AuthorizationBlock
- `docsCondition` attrs `{condition, negated: bool, arguments: []}` block container → ConditionBlock
- `docsAudience` attrs `{name}` block container → AudienceBlock
- `docsCallout` attrs `{type: note|tip|info|warning|danger, title?}` block container → Callout
- `docsCards` attrs `{columns: int}` container of docsCard → CardGroup
- `docsCard` attrs `{title?, icon?, href?}` block container → Card
- `docsInclude` attrs `{name}` atom (leaf) → IncludeNode
- `docsComponent` attrs `{name, attributes: {}}` atom → ComponentNode
- `docsValue` attrs `{key, arguments: []}` inline atom → DynamicValue
- `docsAppLink` attrs `{kind: 'link'|'route', key, parameters: []}` inline atom → AppLink
- `docsHtml` attrs `{html}` opaque atom → HtmlBlock. NOT insertable from the editor UI; exists only
  to preserve raw HTML from imported markdown verbatim (rendered as a read-only widget).

Unknown node types in stored JSON: TiptapDocumentParser must fail loudly (UnhandledMatchError-
style), never silently drop content.

### Markdown export spellings
docsGate→`:::can ability="…"` (+ `arguments="a,b"`) · docsCondition→`:::when`/`:::unless` ·
docsAudience→`:::audience name="…"` · docsCallout→`:::note` etc (+ `title="…"`) ·
docsCards/docsCard→`::::cards`/`:::card` (outer fence one colon longer per nesting level) ·
docsInclude→`:::include name="…"` · docsComponent→`<docs-component name="…" … />` ·
docsValue→`{{ value:key args }}` · docsAppLink→`{{ link:key }}`/`{{ route:name }}` ·
docsHtml→raw HTML verbatim · codeBlock title→info string `lang title="…"`.
