# Multi-site Docent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Multiple independent documentation sites in one Docent install, each keyed in config (`sites.docs`, `sites.admin`, …) with its own routes, content, branding, gates, admin panel, and integration closures.

**Architecture:** Laravel manager pattern. A singleton `SiteRegistry` (new facade root) owns durable global/per-site integration registrations. A scoped `SiteServices` builds and memoizes one internally consistent service graph per site for the current application scope, while a scoped `CurrentSite` holds only the request/console selection. Each graph is built from a `SiteConfig` cascade (site entry → top-level shared default → shipped default). Route groups are registered per site with `docent.{key}.*` names; middleware selects the current site per request; console falls back to `docent.default`. Shared DB tables gain a `site` column.

**Tech Stack:** PHP 8.3+, Laravel 12/13 package (Orchestra Testbench 10/11 workbench), Pest for tests, Pint for style, PHPStan for analysis.

**Spec:** `docs/superpowers/specs/2026-07-17-multi-site-design.md` — read it before starting.

## Global Constraints

- `declare(strict_types=1);` and `final` classes everywhere, matching existing code style.
- The full suite must be green at the end of every task: `composer test` (Pest). Also run `composer lint` and `composer analyse` before each commit.
- Route names are **always** site-keyed: `docent.{key}.*` (including the shipped `docs` site). No bare `docent.*` names survive Task 6.
- Site-only config sections that never cascade from top level: `name`, `description`, `route`, `filesystem`, `admin`, `navigation`, `layouts`.
- The `docs` site keeps `filesystem.path: null → resource_path('docs')`; any **other** site key without an explicit `filesystem.path` throws at build time and is a `docent:check` error.
- Site keys must match `[A-Za-z0-9_-]+`; they are embedded in route names, middleware parameters, and cache prefixes. An explicitly configured `docent.default` that does not name a site is an error, never a silent fallback.
- Singleton objects must never capture scoped or per-site services. In particular, `CodeBlockRenderer` and `InsightSummary` belong to the per-site graph because they use site cache/data.
- Commit after every task. Commit messages: conventional prefix, imperative, **no co-author or "Generated with" lines**.
- New classes live in `src/Sites/` (namespace `STS\Docent\Sites`).

---

### Task 1: SiteConfig value object

**Files:**
- Create: `src/Sites/SiteConfig.php`
- Test: `tests/Unit/Sites/SiteConfigTest.php`

**Interfaces:**
- Produces: `new SiteConfig(string $key, array $config)` where `$config` is the full `docent` config array; `->key` (public readonly string); `->get(string $path, mixed $default = null): mixed`.

- [x] **Step 1: Write the failing test**

```php
<?php

use STS\Docent\Sites\SiteConfig;

it('reads a value from the site entry first', function () {
    $config = new SiteConfig('admin', [
        'theme' => ['accent' => '#111111'],
        'sites' => ['admin' => ['theme' => ['accent' => '#ff0000']]],
    ]);

    expect($config->get('theme.accent'))->toBe('#ff0000');
});

it('cascades a shared key to the top level when the site omits it', function () {
    $config = new SiteConfig('admin', [
        'search' => ['enabled' => false],
        'sites' => ['admin' => []],
    ]);

    expect($config->get('search.enabled'))->toBeFalse();
});

it('falls back to the caller default when neither level sets a shared key', function () {
    $config = new SiteConfig('admin', ['sites' => ['admin' => []]]);

    expect($config->get('cache.prefix', 'docent'))->toBe('docent');
});

it('never cascades site-only sections to the top level', function () {
    $config = new SiteConfig('admin', [
        'name' => 'Global Name',
        'route' => ['prefix' => 'docs'],
        'sites' => ['admin' => []],
    ]);

    expect($config->get('name'))->toBeNull()
        ->and($config->get('route.prefix', 'fallback'))->toBe('fallback');
});

it('reads site-only sections from the site entry', function () {
    $config = new SiteConfig('admin', [
        'sites' => ['admin' => ['route' => ['prefix' => 'admin/docs']]],
    ]);

    expect($config->get('route.prefix'))->toBe('admin/docs');
});

it('exposes its key', function () {
    expect((new SiteConfig('docs', []))->key)->toBe('docs');
});
```

- [x] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest tests/Unit/Sites/SiteConfigTest.php`
Expected: FAIL — `Class "STS\Docent\Sites\SiteConfig" not found`

- [x] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use Illuminate\Support\Arr;

/**
 * Read-only view of one site's effective configuration. Lookups cascade
 * site entry → top-level shared default → caller default. The SITE_ONLY
 * sections never fall back to the top level — a route prefix or content
 * path is meaningless as a cross-site default.
 *
 * An explicit null in a site entry is indistinguishable from an absent key
 * and cascades; sites cannot null-out a shared value, only replace it.
 */
final class SiteConfig
{
    private const SITE_ONLY = ['name', 'description', 'route', 'filesystem', 'admin', 'navigation', 'layouts'];

    /**
     * @param  array<string, mixed>  $config  The full `docent` config array.
     */
    public function __construct(
        public readonly string $key,
        private readonly array $config,
    ) {}

    public function get(string $path, mixed $default = null): mixed
    {
        $value = Arr::get($this->config, 'sites.'.$this->key.'.'.$path);

        if ($value !== null) {
            return $value;
        }

        if ($this->isSiteOnly($path)) {
            return $default;
        }

        return Arr::get($this->config, $path, $default) ?? $default;
    }

    private function isSiteOnly(string $path): bool
    {
        $section = str_contains($path, '.') ? strstr($path, '.', true) : $path;

        return in_array($section, self::SITE_ONLY, true);
    }
}
```

- [x] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest tests/Unit/Sites/SiteConfigTest.php`
Expected: PASS (6 tests)

- [ ] **Step 5: Lint, analyse, full suite, commit**

```bash
composer lint && composer analyse && composer test
git add src/Sites/SiteConfig.php tests/Unit/Sites/SiteConfigTest.php
git commit -m "feat: SiteConfig cascade for per-site configuration"
```

---

### Task 2: SiteRef, context site identity, and registry fallback

**Files:**
- Create: `src/Sites/SiteRef.php`
- Modify: `src/Runtime/DocumentationContext.php` (add constructor property)
- Modify: `src/Runtime/IntegrationRegistry.php` (parent fallback)
- Test: `tests/Unit/Sites/SiteRefTest.php`, `tests/Unit/Runtime/RegistryFallbackTest.php`

**Interfaces:**
- Produces: `new SiteRef(string $key, string $name)` with public readonly `key`/`name`; `DocumentationContext->site: ?SiteRef` (new optional constructor arg, after `$gate`); `new IntegrationRegistry(?Closure $classResolver = null, ?IntegrationRegistry $parent = null)` — every `has*`/`resolve*`/`valueLabel` falls back to `$parent` on local miss, `suggestionsFor` merges parent-then-local (dedup, cap 5), `describe()` merges with local winning by `name`.

- [x] **Step 1: Write the failing tests**

`tests/Unit/Sites/SiteRefTest.php`:

```php
<?php

use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Sites\SiteRef;

it('exposes key and name', function () {
    $ref = new SiteRef('admin', 'Admin Docs');

    expect($ref->key)->toBe('admin')->and($ref->name)->toBe('Admin Docs');
});

it('rides on the documentation context and defaults to null', function () {
    expect((new DocumentationContext)->site)->toBeNull();

    $context = new DocumentationContext(site: new SiteRef('docs', 'Docs'));

    expect($context->site->key)->toBe('docs');
});
```

`tests/Unit/Runtime/RegistryFallbackTest.php`:

```php
<?php

use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

it('falls back to the parent registry on a local miss', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => 'Global Plan');

    $site = new IntegrationRegistry(parent: $global);

    expect($site->hasValue('plan'))->toBeTrue()
        ->and($site->resolveValue('plan', new DocumentationContext))->toBe('Global Plan');
});

it('prefers a site-scoped registration over the global one', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => 'Global Plan');

    $site = new IntegrationRegistry(parent: $global);
    $site->value('plan', fn () => 'Admin Plan');

    expect($site->resolveValue('plan', new DocumentationContext))->toBe('Admin Plan');
});

it('merges suggestions from both layers, local last, capped at five', function () {
    $global = new IntegrationRegistry;
    $global->suggest('billing.*', ['billing/overview']);

    $site = new IntegrationRegistry(parent: $global);
    $site->suggest('billing.*', ['billing/admin', 'billing/overview']);

    expect($site->suggestionsFor('billing.index'))->toBe(['billing/overview', 'billing/admin']);
});

it('describes merged metadata with local winning by name', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => '', 'Global label');
    $global->value('seats', fn () => '', 'Seats');

    $site = new IntegrationRegistry(parent: $global);
    $site->value('plan', fn () => '', 'Site label');

    $values = collect($site->describe()['values'])->keyBy('name');

    expect($values['plan']['label'])->toBe('Site label')
        ->and($values['seats']['label'])->toBe('Seats');
});

it('does not inherit a parent label when a local registration omits one', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => '', 'Global label');

    $site = new IntegrationRegistry(parent: $global);
    $site->value('plan', fn () => '');

    expect($site->valueLabel('plan'))->toBe('plan');
});
```

- [x] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Unit/Sites/SiteRefTest.php tests/Unit/Runtime/RegistryFallbackTest.php`
Expected: FAIL — `SiteRef` not found; unknown named argument `parent`.

- [x] **Step 3: Implement**

`src/Sites/SiteRef.php`:

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

/**
 * The current site's identity as seen by integration closures — enough to
 * branch on (`$context->site->key`) without exposing the service graph.
 */
final class SiteRef
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
    ) {}
}
```

`DocumentationContext`: add the property after `$gate` (keeps every existing positional call site valid):

```php
public function __construct(
    public readonly ?Authenticatable $user = null,
    public readonly ?Request $request = null,
    public readonly array $parameters = [],
    public readonly ?string $audience = null,
    private readonly ?Closure $gate = null,
    public readonly ?SiteRef $site = null,
) {}
```

(import `STS\Docent\Sites\SiteRef`.)

`IntegrationRegistry`: constructor becomes

```php
public function __construct(?Closure $classResolver = null, private readonly ?self $parent = null)
{
    $this->classResolver = $classResolver ?? static fn (string $class): object => new $class;
}
```

Then, in place, per method — the pattern is identical everywhere (local first, parent on miss):

```php
public function hasValue(string $name): bool
{
    return isset($this->values[$name]) || ($this->parent?->hasValue($name) ?? false);
}

public function resolveValue(string $name, DocumentationContext $context, array $arguments = []): ?string
{
    $registered = $this->values[$name] ?? null;

    if ($registered === null) {
        return $this->parent?->resolveValue($name, $context, $arguments);
    }

    return (string) $this->invoke($registered->resolver, [$context, ...$arguments]);
}
```

Apply the same two-line change to `hasCondition`/`resolveCondition`, `hasLink`/`resolveLink`, `hasComponent`/`resolveComponent`, and `hasAudience`/`resolveAudience`. For `valueLabel`, a local registration wins as a unit: return its label or `$name`; consult the parent only when no local value is registered. `suggestionsFor` merges parent results first:

```php
public function suggestionsFor(string $page): array
{
    $slugs = $this->parent?->suggestionsFor($page) ?? [];

    foreach ($this->suggestions as $pattern => $suggestions) {
        if (Str::is($pattern, $page)) {
            array_push($slugs, ...$suggestions);
        }
    }

    return array_slice(array_values(array_unique($slugs)), 0, 5);
}
```

`describe()` delegates to a merge: for each kind, start from `$this->parent?->describe()[$kind] ?? []`, index by `name`, overlay local entries, return `array_values`.

- [x] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Unit/Sites/SiteRefTest.php tests/Unit/Runtime/RegistryFallbackTest.php`
Expected: PASS

- [ ] **Step 5: Lint, analyse, full suite, commit**

```bash
composer lint && composer analyse && composer test
git add -A src tests
git commit -m "feat: site identity on context and parent-fallback registry overlay"
```

---

### Task 3: Route/config indirection on DocentManager (mechanical, behavior-preserving)

Every internal read of `config('docent.*')` and every `route('docent.*')` call moves behind two seams on `DocentManager`, so the Task 6 flip changes exactly one class. Behavior is unchanged in this task.

**Files:**
- Modify: `src/DocentManager.php` (add three methods; swap its own 20 config reads and 7 route calls onto them)
- Modify: every file in the sweep tables below
- Test: existing suite (this is a pure refactor — green suite is the test)

**Interfaces:**
- Produces on `DocentManager`:
  - `public function config(string $path, mixed $default = null): mixed` — Task 3 body: `return config('docent.'.$path, $default);`
  - `public function routeName(string $suffix): string` — Task 3 body: `return 'docent.'.$suffix;`
  - `public function route(string $suffix, array $parameters = []): string` — body: `return route($this->routeName($suffix), $parameters);`

- [x] **Step 1: Add the three methods to `DocentManager`** (near `siteName()`), with the Task 3 bodies above and a docblock noting they become site-aware in the flip task.

- [x] **Step 2: Sweep `DocentManager` itself.** Replace all its own reads (inventory: lines 268, 279, 282, 328, 340, 417, 427–431, 444, 451, 618, 624–625, 641, 648, 656, 727, 745, 1100, 1102, 1332, 1394, 1399 as of the spec commit): `config('docent.X', $d)` → `$this->config('X', $d)`; `route('docent.Y', $p)` → `$this->route('Y', $p)`. Example: `layoutView()` becomes `$configured = $this->config('layouts.'.$layout);`, `markdownUrl()` becomes `return $this->route('show', ['slug' => ...]);`.

- [x] **Step 3: Sweep classes that already hold the manager.** In each, replace `config('docent.X', $d)` → `$this->docent->config('X', $d)` (or the injected variable name) and `route('docent.Y', $p)` → `$this->docent->route('Y', $p)`:
  - `src/Http/Controllers/PageController.php` (3 reads)
  - `src/Http/Controllers/WidgetController.php` (4 reads)
  - `src/Http/Controllers/SearchController.php` (2 reads, 1 route)
  - `src/Http/Controllers/AskController.php` (10 reads)
  - `src/Http/Controllers/AskConversationController.php` (1 read)
  - `src/Ai/AiRetriever.php` (2 reads)
  - `src/Ai/AiCorpusBuilder.php` (5 reads)

- [x] **Step 4: Give the remaining service classes a manager (or explicit values).**
  - `src/Navigation/NavigationBuilder.php`: inject `SiteConfig` (not `DocentManager`, which would create a constructor cycle) and replace its three `navigation.*` reads with `$siteConfig->get(...)`. Update its provider binding with `new SiteConfig('docs', ...)` until Task 5 owns construction. Before Task 4 moves the config shape, the binding must mirror the legacy top-level `navigation` block into `sites.docs.navigation` so this task remains behavior-preserving.
  - `src/Ai/AiAnswerService.php`: constructor gains `DocentManager $docent`; its 3 reads become `$docent->config('ai.provider')` etc. Update the provider's `AiAnswerService` binding to pass `$app->make(DocentManager::class)`.
  - `src/Ai/AiQuestionLogger.php`, `src/Ai/AiConversationStore.php`, `src/Insights/InsightRecorder.php`: same treatment — constructor `DocentManager $docent` (promoted readonly), reads through it, provider bindings updated to `static fn (Application $app) => new X($app->make(DocentManager::class), ...)`. These become per-site services in Task 5, so the dependency is the point, not churn.
  - `src/Http/Controllers/UploadsController.php` and `src/Http/Controllers/Admin/UploadController.php`: inject `DocentManager $docent` (method or constructor, matching each file's current style); swap the 3 reads and 1 `route('docent.upload')` call.
  - `src/Http/Controllers/Admin/Concerns/InteractsWithPages.php`: its `config('docent.database.connection')` read → `$this->docent()->config('database.connection')` — add a small `docent(): DocentManager` accessor (`app(DocentManager::class)`) on the concern if the host controllers don't already hold one.
  - `src/Console/InstallCommand.php`, `src/Console/CheckCommand.php`: resolve the manager (`$docent = $this->laravel->make(DocentManager::class);`) and swap reads.
  - `src/Validation/Checks/NavigationLinkCheck.php`, `UnknownIconCheck.php`, `AiCorpusSizeCheck.php`: `CheckContext` already carries repository/parser/registry — add `public readonly ?DocentManager $docent` to `src/Validation/CheckContext.php` (default null), populate it at both construction sites (`DocentManager::draftIssues()` passes `$this`, `CheckCommand` passes the resolved manager), and swap the checks' reads to `$context->docent->config(...)`.

- [x] **Step 5: Sweep the Blade views.** Every docs-served view already receives `$docent` (the manager). Replace:
  - `resources/views/layout.blade.php`: `config('docent.ai.enabled', false)` → `$docent->config('ai.enabled', false)`; `route('docent.ask')` / `route('docent.ask.feedback')` → `$docent->route('ask')` / `$docent->route('ask.feedback')`.
  - `resources/views/partials/search.blade.php`, `resources/views/widget/layout.blade.php`, `resources/views/components/search-box.blade.php`: same pattern (`ai.enabled`, `route('docent.search'|'docent.widget.suggestions'|'docent.ask'|'docent.ask.feedback')`).
  - `resources/views/admin.blade.php`, `resources/views/admin-insights.blade.php`: `route('docent.admin*')` → `$docent->route('admin*')`; `config('docent.insights.*')` → `$docent->config('insights.*')`. If a view lacks `$docent`, add it to that controller's view payload.
  - `resources/views/components/widget.blade.php`: keeps reading via the facade for now — change `@if(config('docent.widget.enabled', false))` to `@if(\STS\Docent\Facades\Docent::config('widget.enabled', false))`. (Task 9 reworks this component properly.)
  - Leave `src/DocentServiceProvider.php` reads alone — the provider is rewritten wholesale in Task 6.

- [x] **Step 6: Full suite green, then commit**

Run: `composer lint && composer analyse && composer test`
Expected: PASS — zero behavior change. If anything fails, the sweep introduced a typo; fix it, don't paper over it. As of this task, focused analysis of every changed file passes; the repository-wide analysis still reports only the two pre-existing `NavigationBuilder` findings (`nullsafe.neverNull` and `nullCoalesce.offset`).

```bash
git add -A src resources
git commit -m "refactor: route all docent config and route-name reads through DocentManager"
```

---

### Task 4: Restructure config/docent.php and wire SiteConfig into DocentManager

Still single-site (`docs`), still bare route names — but the config file takes its final shape and `DocentManager::config()` resolves through `SiteConfig`.

**Files:**
- Modify: `config/docent.php`, `src/DocentManager.php`, `src/DocentServiceProvider.php`, `tests/TestCase.php`, ~31 test call sites
- Test: existing suite + `tests/Feature/SiteConfigCascadeTest.php`

**Interfaces:**
- Consumes: `SiteConfig` from Task 1.
- Produces: `DocentManager::__construct(...)` gains `private readonly SiteConfig $siteConfig` (last parameter); `DocentManager::key(): string` returns `$this->siteConfig->key`; `DocentManager::config()` body becomes `return $this->siteConfig->get($path, $default);`.

- [x] **Step 1: Rewrite `config/docent.php`.** Keep every existing block and its doc comment. New top-level shape — shared sections stay at top level verbatim (`database`, `authorization`, `content`, `search`, `ai`, `insights`, `widget`, `cache`, `theme`), site-only sections move inside `sites.docs`:

```php
return [
    /* ... existing doc comments preserved/adapted ... */
    'default' => 'docs',

    // ── Shared defaults (overridable per site) ─────────────────────────
    'database' => [...],        // unchanged content
    'authorization' => [...],   // unchanged
    'content' => [...],         // unchanged
    'search' => [...],          // unchanged
    'ai' => [...],              // unchanged
    'insights' => [...],        // unchanged
    'widget' => [...],          // unchanged
    'cache' => [...],           // unchanged
    'theme' => [...],           // unchanged

    // ── Sites ──────────────────────────────────────────────────────────
    'sites' => [
        'docs' => [
            'name' => env('DOCENT_NAME', config('app.name').' Docs'),
            'description' => env('DOCENT_DESCRIPTION'),
            'route' => ['prefix' => 'docs', 'domain' => null, 'middleware' => ['web']],
            'filesystem' => ['path' => null],
            'admin' => ['enabled' => false, 'path' => 'admin', 'gate' => 'viewDocentAdmin', 'disk' => 'public', 'uploads' => ['public_cache' => false]],
            'navigation' => ['default_section' => 'Documentation', 'links' => [], 'topbar' => []],
            'layouts' => [],
        ],
    ],
];
```

Add a header comment on `sites` explaining: keys are host-chosen; every shared section above may be repeated inside a site entry to override it; site-only sections (`name`, `description`, `route`, `filesystem`, `admin`, `navigation`, `layouts`) live only here.

- [x] **Step 2: Wire SiteConfig into the manager.** In `DocentServiceProvider::register()`, the `DocentManager` scoped closure gains a final argument: `new SiteConfig('docs', (array) $app['config']->get('docent', []))`. `DocentManager::config()` body becomes `return $this->siteConfig->get($path, $default);`; add `public function key(): string { return $this->siteConfig->key; }`. Update the provider's own remaining reads (route group, admin group, feature toggles, about command, `FilesystemRepository` binding, `NavigationBuilder` link resolver) to build one `$site = new SiteConfig('docs', ...)` locally and read `$site->get('route.prefix', 'docs')` etc. `FilesystemRepository` path resolution becomes `$site->get('filesystem.path') ?? $app->resourcePath('docs')`.

- [x] **Step 3: Update the tests' config keys.** In `tests/TestCase.php::defineEnvironment()`: `docent.filesystem.path` → `docent.sites.docs.filesystem.path`; `docent.name` → `docent.sites.docs.name`. Then sweep the suite for the site-only keys (`grep -rn "set('docent\.\(name\|description\|route\|filesystem\|admin\.\|admin'\|navigation\|layouts\)" tests`): prepend `sites.docs.` to each — e.g. `config()->set('docent.route.prefix', 'help')` → `config()->set('docent.sites.docs.route.prefix', 'help')`. Shared keys (`ai.*`, `theme.*`, `search.*`, `widget.*`, `insights.*`, `database.*`, `content.*`, `authorization.*`, `cache.*`) stay top-level — that's the cascade working.

- [x] **Step 4: Add a cascade feature test**, `tests/Feature/SiteConfigCascadeTest.php`:

```php
<?php

use STS\Docent\DocentManager;

it('site entry overrides the shared top level', function () {
    config()->set('docent.theme.accent', '#111111');
    config()->set('docent.sites.docs.theme.accent', '#ff0000');
    $this->app->forgetScopedInstances();

    expect($this->app->make(DocentManager::class)->accent())->toBe('#ff0000');
});

it('shared top level applies when the site omits the key', function () {
    config()->set('docent.search.enabled', false);
    $this->app->forgetScopedInstances();

    expect($this->app->make(DocentManager::class)->config('search.enabled', true))->toBeFalse();
});
```

- [x] **Step 5: Full suite, then commit**

Run: `composer lint && composer analyse && composer test`
Expected: PASS. As of this task, formatting, focused analysis, and the full test suite pass; repository-wide analysis still reports only the two pre-existing `NavigationBuilder` findings recorded in Task 3.

```bash
git add -A config src tests
git commit -m "feat: restructure config into shared defaults plus keyed sites"
```

---

### Task 5: Durable SiteRegistry, scoped current site, and per-site service graphs

The singleton registry owns durable registration overlays, while scoped services build and hold each site's request-safe graph. They are not yet wired into the existing container aliases (that's Task 6), so they are fully feature-testable in isolation.

**Files:**
- Create: `src/Sites/SiteRegistry.php`, `src/Sites/SiteServices.php`, `src/Sites/CurrentSite.php`, `src/Http/Middleware/SetCurrentSite.php`
- Test: `tests/Feature/Sites/SiteRegistryTest.php`

**Interfaces:**
- Consumes: `SiteConfig`, `SiteRef`, parent-fallback `IntegrationRegistry`, `DocentManager::key()`.
- Produces on singleton `SiteRegistry`:
  - `keys(): list<string>`, `has(string $key): bool`, `defaultKey(): string` (config `docent.default`, falling back to the first `sites` key)
  - `site(?string $key = null): DocentManager` — null means default; unknown key throws `InvalidArgumentException`
  - `siteConfig(string $key): SiteConfig`, `registryFor(string $key): IntegrationRegistry` (overlay, parent = global)
  - `service(string $class): object` and `serviceFor(string $key, string $class): object` — delegate to scoped `SiteServices`
  - Global registration proxies returning `$this`: `condition()`, `value()`, `link()`, `component()`, `audience()`, `suggest()` — each delegates to the **global** `IntegrationRegistry`
  - `__call($method, $arguments)` — proxies anything else to `current()`
- Produces on scoped `CurrentSite`: `set(string $key)`, `key(): string` (selected key or validated default).
- Produces on scoped `SiteServices`: `current()`, `site(?string $key = null)`, `service(string $class)`, and `serviceFor(string $key, string $class)`. It memoizes the **entire** graph for a key in one assignment before returning any member.
- Produces: `SetCurrentSite` middleware — `handle(Request $request, Closure $next, string $key)` calls `$currentSite->set($key)`.

- [x] **Step 1: Write the failing tests**

```php
<?php

use STS\Docent\DocentManager;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Sites\CurrentSite;
use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Sites\SiteServices;

function twoSiteConfig(): void
{
    config()->set('docent.default', 'public');
    config()->set('docent.sites', [
        'public' => [
            'name' => 'Help Center',
            'route' => ['prefix' => 'help', 'middleware' => ['web']],
            'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
        ],
        'admin' => [
            'name' => 'Admin Docs',
            'route' => ['prefix' => 'admin/docs', 'middleware' => ['web', 'auth']],
            'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
        ],
    ]);
}

it('builds one manager per site, lazily and memoized', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);

    $public = $registry->site('public');
    $admin = $registry->site('admin');

    expect($public)->toBeInstanceOf(DocentManager::class)
        ->and($public->key())->toBe('public')
        ->and($admin->key())->toBe('admin')
        ->and($registry->site('public'))->toBe($public);
});

it('throws on an unknown site key', function () {
    twoSiteConfig();

    $this->app->make(SiteRegistry::class)->site('nope');
})->throws(InvalidArgumentException::class);

it('resolves current from the set key and falls back to default', function () {
    twoSiteConfig();
    $current = $this->app->make(CurrentSite::class);
    $services = $this->app->make(SiteServices::class);

    expect($current->key())->toBe('public')
        ->and($services->current()->key())->toBe('public');

    $current->set('admin');

    expect($services->current()->key())->toBe('admin');
});

it('gives each site its own service graph', function () {
    twoSiteConfig();
    $services = $this->app->make(SiteServices::class);
    $publicSearch = $services->serviceFor('public', SearchEngine::class);

    expect($services->serviceFor('admin', SearchEngine::class))->not->toBe($publicSearch);
});

it('returns services from one internally consistent graph', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);

    expect($registry->serviceFor('public', DocentManager::class))->toBe($registry->site('public'));
    // Also assert through reflection or a narrow test seam that SearchEngine's
    // manager/cache dependencies are the same objects returned for this graph.
});

it('keeps site registrations after scoped instances are forgotten', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);
    $registry->site('admin')->value('plan', fn () => 'Admin');

    $this->app->forgetScopedInstances();

    expect($registry->registryFor('admin')->hasValue('plan'))->toBeTrue();
});

it('registers globally at the root and per-site through site()', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);
    $registry->value('plan', fn () => 'Global');
    $registry->site('admin')->value('plan', fn () => 'Admin');

    $publicRegistry = $registry->registryFor('public');
    $adminRegistry = $registry->registryFor('admin');
    $context = new STS\Docent\Runtime\DocumentationContext;

    expect($publicRegistry->resolveValue('plan', $context))->toBe('Global')
        ->and($adminRegistry->resolveValue('plan', $context))->toBe('Admin');
});

it('requires an explicit filesystem path for non-docs sites', function () {
    config()->set('docent.sites', ['extra' => ['route' => ['prefix' => 'extra']]]);

    $this->app->make(SiteRegistry::class)->site('extra')->repository()->all()->current();
})->throws(RuntimeException::class);
```

- [x] **Step 2: Run to verify failure** — `vendor/bin/pest tests/Feature/Sites/SiteRegistryTest.php` — FAIL, class not found.

- [x] **Step 3: Implement `SiteRegistry`.** Skeleton (the `build()` body ports the provider's current wiring verbatim, per site):

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use RuntimeException;
use STS\Docent\DocentManager;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * The front door for multi-site Docent and the facade root. Builds and holds
 * one service graph per configured site; registration calls at this level are
 * global (all sites), while Docent::site('x')->… scopes to one site. Anything
 * not defined here proxies to the current site's manager.
 *
 * @mixin DocentManager
 */
final class SiteRegistry
{
    /** @var array<string, IntegrationRegistry> */
    private array $registries = [];

    public function __construct(
        private readonly Application $app,
        private readonly IntegrationRegistry $global,
    ) {}

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(strval(...), array_keys((array) $this->app['config']->get('docent.sites', [])));
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    public function defaultKey(): string
    {
        $configured = $this->app['config']->get('docent.default');
        if (is_string($configured) && $configured !== '') {
            if (! $this->has($configured)) {
                throw new InvalidArgumentException("Unknown default Docent site [{$configured}].");
            }

            return $configured;
        }

        return $this->keys()[0] ?? throw new InvalidArgumentException('No Docent sites are configured.');
    }

    public function current(): DocentManager
    {
        return $this->app->make(SiteServices::class)->current();
    }

    public function site(?string $key = null): DocentManager
    {
        return $this->app->make(SiteServices::class)->site($key);
    }

    public function service(string $class): object
    {
        return $this->app->make(SiteServices::class)->service($class);
    }

    public function serviceFor(string $key, string $class): object
    {
        return $this->app->make(SiteServices::class)->serviceFor($key, $class);
    }

    public function siteConfig(string $key): SiteConfig
    {
        return new SiteConfig($key, (array) $this->app['config']->get('docent', []));
    }

    public function registryFor(string $key): IntegrationRegistry
    {
        return $this->registries[$key] ??= new IntegrationRegistry(
            fn (string $class): object => $this->app->make($class),
            $this->global,
        );
    }

    // Global registration — mirrors DocentManager's fluent API, targets all sites.
    public function value(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
    {
        $this->global->value($name, $resolver, $label, $description);

        return $this;
    }
    // …identical one-liners for condition(), link(), component(), audience(), suggest().

    public function __call(string $method, array $arguments): mixed
    {
        return $this->current()->{$method}(...$arguments);
    }

}
```

`SiteServices` owns `$services` and the `buildAll()` implementation. Its lookup must cache the full graph atomically:

```php
public function serviceFor(string $key, string $class): object
{
    if (! $this->sites->has($key)) {
        throw new InvalidArgumentException("Unknown Docent site [{$key}].");
    }

    if (! isset($this->services[$key])) {
        $this->services[$key] = $this->buildAll($key);
    }

    return $this->services[$key][$class]
        ?? throw new InvalidArgumentException("Service [{$class}] is not part of the Docent site graph.");
}

private function buildAll(string $key): array
    {
        // Ports DocentServiceProvider::register()'s current wiring, per site:
        // SiteConfig → FilesystemRepository (path rule below) → DatabaseRepository/
        // CompositeRepository when database.enabled → DocentCache with prefix
        // "{cache.prefix}:{$key}" → registryFor($key) → NavigationBuilder (url
        // resolver uses "docent.{$key}.…" route names) → DocentManager (with
        // SiteConfig) → SearchIndexer/SearchEngine → AiRetriever/AiCorpusBuilder/
        // AiAnswerService/AiQuestionLogger/AiConversationStore → InsightRecorder.
        // Shared, site-agnostic services still come from the container:
        // DocumentParser, ContentHtmlSanitizer, PrismGuard, DocumentationMode.
        // Construct CodeBlockRenderer with this graph's DocentCache and construct
        // InsightSummary as a per-site instance; neither is a singleton. Task 7
        // adds the site's key/connection to InsightSummary when DB scoping lands.
    }
}
```

The filesystem path rule inside `buildAll()`:

```php
$path = $config->get('filesystem.path');

if ($path === null && $key !== 'docs') {
    throw new RuntimeException("Docent site [{$key}] has no filesystem.path configured.");
}

$filesystem = new FilesystemRepository($path ?? $this->app->resourcePath('docs'));
```

`SetCurrentSite` (`src/Http/Middleware/SetCurrentSite.php`):

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use STS\Docent\Sites\CurrentSite;

final class SetCurrentSite
{
    public function __construct(private readonly CurrentSite $currentSite) {}

    public function handle(Request $request, Closure $next, string $key): mixed
    {
        $this->currentSite->set($key);

        return $next($request);
    }
}
```

Register `SiteRegistry` as a **singleton**, and `CurrentSite` plus `SiteServices` as **scoped**. This keeps boot-time site registrations durable while ensuring request selection and mutable service state are Octane-safe. Validate site keys before using them in route names or middleware parameters.

- [x] **Step 4: Run the new tests** — `vendor/bin/pest tests/Feature/Sites/SiteRegistryTest.php` — PASS. Full suite still green (nothing else consumes the registry yet).

- [x] **Step 5: Commit**

```bash
composer lint && composer analyse && composer test
git add -A src tests
git commit -m "feat: SiteRegistry builds one service graph per configured site"
```

---

### Task 6: The flip — per-site routes, keyed names, container delegation, facade root

**Files:**
- Modify: `src/DocentServiceProvider.php` (register + registerRoutes + registerAdminRoutes + about), `src/DocentManager.php` (`routeName()`), `src/Facades/Docent.php`, `tests/TestCase.php` (if needed), any test referencing `route('docent.…')` (1 site) or asserting route names

**Interfaces:**
- Consumes: `SiteRegistry`, `SetCurrentSite`.
- Produces: route names `docent.{key}.{suffix}` for every route; `Docent` facade accessor = `SiteRegistry::class`; container bindings for `DocentManager`, `DocumentationRepository`, `FilesystemRepository`, `NavigationBuilder`, `DocentCache`, `CodeBlockRenderer`, `SearchIndexer`, `SearchEngine`, `AiRetriever`, `AiCorpusBuilder`, `AiAnswerService`, `AiQuestionLogger`, `AiConversationStore`, `InsightRecorder`, and `InsightSummary` all resolve the **current site's** instance via `SiteRegistry::service()`.

- [x] **Step 1: Flip `DocentManager::routeName()`** to `return 'docent.'.$this->key().'.'.$suffix;`.

- [x] **Step 2: Rewrite the provider's `register()`** so every per-site class binding delegates: `$this->app->scoped(DocentManager::class, static fn (Application $app) => $app->make(SiteRegistry::class)->current());` and for each other per-site class `$this->app->scoped(SearchEngine::class, static fn (Application $app) => $app->make(SiteRegistry::class)->service(SearchEngine::class));` (same one-liner per class in the Interfaces list). Delete the now-dead direct construction closures — `SiteServices::buildAll()` is the single wiring site. Keep only genuinely shared bindings (`IntegrationRegistry`, `DocumentationMode`, `DocumentParser`, `ContentHtmlSanitizer`, `PrismGuard`) as they are.

- [x] **Step 3: Rewrite `registerRoutes()`** as a loop. For each `$key` in `config('docent.sites')`:

```php
foreach (array_keys((array) config('docent.sites', [])) as $key) {
    $site = new SiteConfig((string) $key, (array) config('docent', []));

    Route::group([
        'prefix' => $site->get('route.prefix', 'docs'),
        'domain' => $site->get('route.domain'),
        'middleware' => [...(array) $site->get('route.middleware', ['web']), SetCurrentSite::class.':'.$key],
        'as' => 'docent.'.$key.'.',
    ], function () use ($site, $key): void {
        Route::get('/', [PageController::class, 'home'])->name('home');
        // …every existing route, with the bare name('docent.X') shortened to name('X')
        // …feature toggles read from $site->get('search.enabled', true) etc.
        // …admin subgroup: gate/path from $site->get('admin.gate'|'admin.path')
    });
}
```

The `'as' => 'docent.{$key}.'` group prefix plus shortened inner `->name('show')` etc. produces exactly `docent.{key}.show`. Port `registerAdminRoutes()` the same way (it becomes a closure receiving `$site`). Update `registerAboutCommand()` to emit one `Pages`/`Route Prefix`/`Database` line per site key.

- [x] **Step 4: Repoint the facade.** `src/Facades/Docent.php` accessor returns `SiteRegistry::class`; update the docblock: add `@method static DocentManager site(?string $key = null)` and `@mixin \STS\Docent\DocentManager`, keep the registration method annotations (they now hit the global registry).

- [x] **Step 5: Fix test fallout.** Expected: the single `route('docent.…')` test reference becomes `route('docent.docs.…')`; any test resolving `DocentManager::class` still works via delegation; `TestCase` needs no change beyond Task 4. Run the full suite and fix name-string assertions only — a behavioral failure here means the flip broke something real; stop and fix the source, not the test.

- [x] **Step 6: Full suite, commit**

```bash
composer lint && composer analyse && composer test
git add -A src tests
git commit -m "feat!: per-site route groups with docent.{site}.* names and SiteRegistry facade root"
```

---

### Task 7: Database site scoping

**Files:**
- Modify: `database/migrations/2026_01_01_000000_create_docent_pages_table.php`, `..._000002_create_docent_ai_questions_table.php`, `..._000003_create_docent_insight_events_table.php` (edit the create migrations in place — 0.1.0, no alter migrations)
- Modify: `src/Content/Models/DocentPage.php`, `src/Content/Repositories/DatabaseRepository.php`, `src/DocentManager.php` (admin queries), `src/Http/Controllers/Admin/Concerns/InteractsWithPages.php`, `src/Insights/InsightRecorder.php`, `src/Insights/InsightSummary.php`, `src/Ai/AiQuestionLogger.php`
- Test: `tests/Feature/Sites/DatabaseSiteScopingTest.php`

**Interfaces:**
- Consumes: `DocentManager::key()`.
- Produces: `DocentPage::write(string $slug, string $content, array $frontMatter = [], ?int $authorId = null, string $format = 'markdown', string $site = 'docs', ?string $connection = null): self`; `DocentPage::forSite(?string $connection, string $site): Builder` (static, replaces bare `DocentPage::on($connection)` at every read site); equivalent site/connection query seams for `AiQuestion` and `InsightEvent`; `new DatabaseRepository(?string $connection = null, string $site = 'docs')`.

- [x] **Step 1: Write the failing test**

```php
<?php

use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\DatabaseRepository;

beforeEach(function () {
    config()->set('docent.database.enabled', true);
});

it('allows the same slug on two sites and keeps them isolated', function () {
    DocentPage::write('guides/setup', '# Public', [], null, 'markdown', 'public')->publish();
    DocentPage::write('guides/setup', '# Admin', [], null, 'markdown', 'admin')->publish();

    expect(DocentPage::query()->count())->toBe(2)
        ->and((new DatabaseRepository(site: 'public'))->find('guides/setup')->rawContent)->toContain('Public')
        ->and((new DatabaseRepository(site: 'admin'))->find('guides/setup')->rawContent)->toContain('Admin');
});

it('upserts within a site, not across sites', function () {
    DocentPage::write('page', 'one', [], null, 'markdown', 'public');
    DocentPage::write('page', 'two', [], null, 'markdown', 'public');

    expect(DocentPage::query()->where('site', 'public')->count())->toBe(1);
});
```

- [x] **Step 2: Run to verify failure** — unknown column `site` / unknown named parameter.

- [x] **Step 3: Implement.**
  - Migrations: in the pages table, add `$table->string('site')->default('docs');` before `slug`, change `$table->string('slug')->unique()` (or its current form — read the file) to a plain column plus `$table->unique(['site', 'slug']);`. Add `$table->string('site')->default('docs')->index();` to `docent_ai_questions` and `docent_insight_events`.
  - `DocentPage`: `write()` gains trailing `string $site = 'docs', ?string $connection = null` parameters; build its query from a model using that connection, then `firstOrNew(['site' => $site, 'slug' => $slug])`. Add `forSite()`. `revertTo()` must pass `$this->site` and `$this->getConnectionName()` back into `write()` so a revert cannot land in `docs` or on the default connection.
  - `DatabaseRepository`: constructor gains `private readonly string $site = 'docs'`; every `DocentPage::on($this->connection)` becomes `DocentPage::forSite($this->connection, $this->site)`.
  - `DocentManager`: every `DocentPage::on($this->databaseConnection())` (adminTree, adminDetail, adminGroups, removeGroupMeta, exportMarkdown) becomes `DocentPage::forSite($this->databaseConnection(), $this->key())`; every `DocentPage::write(...)` call gains both `site: $this->key()` and `connection: $this->databaseConnection()`.
  - `InteractsWithPages` and admin controllers: same substitution for every direct `DocentPage::on(...)` / `write(...)` usage (grep `DocentPage::` across `src/Http`).
  - `InsightRecorder` / `AiQuestionLogger`: use the current site's connection and include `'site' => $this->docent->key()` in every insert payload. Add the same site+connection predicate to **every** read/update/delete query in `InsightRecorder`, including continuations, interactions, feedback, and pruning.
  - `AskFeedbackController`: inject the manager and retrieve `AiQuestion` through its site+connection query seam before updating feedback. A valid token for another site's question must still produce 404.
  - `InsightSummary` and the admin insights/export controllers: query through the site+connection seam. `InsightSummary` is constructed per site in `SiteServices`, not retained as a singleton.
  - `SiteServices::buildAll()`: pass `site: $key` when constructing `DatabaseRepository`.
  - Provider `databaseSummary()`: count `DocentPage::forSite($connection, $key)` per site.

- [x] **Step 4: Add regression cases for reverting a non-`docs` page, rejecting cross-site AI feedback/insight continuation, and using a per-site non-default database connection. Run the new tests, then the full suite** — PASS. Existing DB tests keep working because every default is `'docs'` and the default site key is `docs`.

- [x] **Step 5: Commit**

```bash
composer lint && composer analyse && composer test
git add -A database src tests
git commit -m "feat!: scope database pages, insights, and AI questions by site"
```

---

### Task 8: Context site threading and scoped-registration feature coverage

**Files:**
- Modify: `src/DocentManager.php` (`contextFor()` passes the site ref)
- Test: `tests/Feature/Sites/SiteScopedClosuresTest.php`

**Interfaces:**
- Consumes: `SiteRef`, `registryFor()` overlays from Task 5, keyed routes from Task 6.
- Produces: `DocentManager::siteRef(): SiteRef` → `new SiteRef($this->key(), $this->siteName())`; `contextFor()` adds `site: $this->siteRef()` to the `DocumentationContext` it builds.

- [x] **Step 1: Write the failing test**

```php
<?php

use STS\Docent\Facades\Docent;

beforeEach(function () {
    config()->set('docent.sites.admin', [
        'name' => 'Admin Docs',
        'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
        'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
    ]);
});

it('exposes the current site on every closure context', function () {
    $seen = [];
    Docent::value('site.probe', function ($context) use (&$seen) {
        $seen[] = $context->site->key;

        return $context->site->name;
    });

    Docent::site('docs')->contextFor(null);
    expect(Docent::site('docs')->registry()->resolveValue('site.probe', Docent::site('docs')->contextFor(null)))->toBe('Fixture Docs');
    expect(Docent::site('admin')->registry()->resolveValue('site.probe', Docent::site('admin')->contextFor(null)))->toBe('Admin Docs');
    expect($seen)->toBe(['docs', 'admin']);
});

it('prefers a site-scoped closure over the global one end to end', function () {
    Docent::value('plan', fn () => 'Global Plan');
    Docent::site('admin')->value('plan', fn () => 'Admin Plan');

    expect(Docent::site('docs')->registry()->resolveValue('plan', Docent::site('docs')->contextFor(null)))->toBe('Global Plan')
        ->and(Docent::site('admin')->registry()->resolveValue('plan', Docent::site('admin')->contextFor(null)))->toBe('Admin Plan');
});
```

- [x] **Step 2: Verify failure** — `$context->site` is null.

- [x] **Step 3: Implement** the two-line manager change (`siteRef()` + `site:` argument in `contextFor()`). Verify `viewerFingerprint()` still isolates caches per site — the site key is already in the cache prefix, so no change needed there; note this in the commit body.

- [x] **Step 4: Run the new test and full suite** — PASS.

- [x] **Step 5: Commit** — `git commit -m "feat: closures receive the current site on the documentation context"`

---

### Task 9: Widget site targeting

**Files:**
- Modify: `resources/views/components/widget.blade.php`, `src/DocentManager.php` (`widgetConfig()` already site-aware via `config()`/`route()` — verify only), `tests/WidgetTestCase.php`
- Test: `tests/Widget/WidgetSiteTest.php`

**Interfaces:**
- Produces: `<x-docent::widget />` renders the **default** site's widget; `<x-docent::widget site="admin" />` renders the admin site's widget (its URLs point at `admin/docs/_widget…`). Unknown `site` throws `InvalidArgumentException` (surface config typos loudly).

- [x] **Step 1: Write the failing test**

```php
<?php

use STS\Docent\Facades\Docent;
```

Configure the admin site in `WidgetTestCase::defineEnvironment()` so it exists **before** the package provider registers routes:

```php
$app['config']->set('docent.sites.admin', [
    'name' => 'Admin Docs',
    'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
    'filesystem' => ['path' => __DIR__.'/fixtures/docs'],
]);
```

Then test the component:

```php

it('targets the requested site', function () {
    $html = $this->blade('<x-docent::widget site="admin" />');

    $html->assertSee('admin/docs/_widget', false);
});

it('defaults to the default site', function () {
    $html = $this->blade('<x-docent::widget />');

    $html->assertSee('docs/_widget', false);
});
```

(Match the assertion style of the existing `tests/Widget` suite — reuse its helpers if it renders the component differently.)

- [x] **Step 2: Verify failure** — unknown attribute / wrong URLs.

- [x] **Step 3: Implement.** Top of `widget.blade.php`:

```blade
@props(['site' => null])
@php($docent = \STS\Docent\Facades\Docent::site($site))
@if($docent->config('widget.enabled', false))
    {{-- existing markup, all config()/route() reads already swapped to $docent-> in Task 3 --}}
@endif
```

`Docent::site(null)` returns the default site (Task 5 signature). Sweep the component body for any remaining facade-level reads from the Task 3 interim state and point them at `$docent`.

- [x] **Step 4: Run tests, full suite** — PASS. **Step 5: Commit** — `git commit -m "feat: widget component targets a configured site"`

---

### Task 10: Console commands and docent:check site rules

**Files:**
- Modify: `src/Console/ClearCommand.php`, `src/Console/CheckCommand.php`, `src/Console/PruneInsightsCommand.php`, `src/Console/InstallCommand.php`
- Create: `src/Validation/Checks/SiteDefinitionCheck.php` (registered wherever `DocsChecker` composes its check list — find via `DocsChecker::references()`)
- Test: `tests/Feature/Sites/ConsoleSiteScopingTest.php`

**Interfaces:**
- Produces: `docent:clear {--site=}` / `docent:check {--site=}` / `docent:insights:prune {--site=}` — no option means **all** sites; with `--site=x` only that site; unknown key → error exit. Each iteration resolves the target explicitly through `SiteRegistry::site($key)` / `serviceFor($key, ...)`; it must not mutate `CurrentSite` and then reuse a scoped alias already cached for another key. `docent:install` scaffolds the default site. `SiteDefinitionCheck` emits: errors for invalid keys, an invalid explicit default, or a non-`docs` site lacking `filesystem.path`; warning when two sites on overlapping domains have equal or prefix-overlapping effective route paths.

- [x] **Step 1: Write the failing test**

```php
<?php

use STS\Docent\Sites\SiteRegistry;

beforeEach(function () {
    config()->set('docent.sites.admin', [
        'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
        'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
    ]);
});

it('clears every site by default and one with --site', function () {
    $sites = $this->app->make(SiteRegistry::class);
    $docsVersion = $sites->site('docs')->cacheVersion();
    $adminVersion = $sites->site('admin')->cacheVersion();

    $this->artisan('docent:clear')->assertSuccessful();

    expect($sites->site('docs')->cacheVersion())->toBeGreaterThan($docsVersion)
        ->and($sites->site('admin')->cacheVersion())->toBeGreaterThan($adminVersion);

    $before = $sites->site('admin')->cacheVersion();
    $this->artisan('docent:clear', ['--site' => 'docs'])->assertSuccessful();

    expect($sites->site('admin')->cacheVersion())->toBe($before);
});

it('rejects an unknown --site', function () {
    $this->artisan('docent:clear', ['--site' => 'nope'])->assertFailed();
});

it('flags a non-docs site without a filesystem path', function () {
    config()->set('docent.sites.admin.filesystem.path', null);

    $this->artisan('docent:check', ['--site' => 'admin'])
        ->expectsOutputToContain('filesystem.path')
        ->assertFailed();
});
```

Add `DocentManager::cacheVersion(): int` (delegates to `DocentCache::version()`) if no equivalent probe exists — check `ClearCommand`'s current test for the established pattern and reuse it instead if there is one.

- [x] **Step 2: Verify failure. Step 3: Implement** the `--site` option + explicit keyed service loop in the three commands. Run `SiteDefinitionCheck` once per command invocation (it validates the whole config); run corpus/page checks once per selected site so global configuration messages are not duplicated. Route overlap means path-prefix overlap, not just exact equality; a domainless route group overlaps every host, while two distinct concrete domains do not.

- [x] **Step 4: Run tests, full suite** — PASS (540 tests, 2,082 assertions; PHPStan clean). **Step 5: Commit** — `git commit -m "feat: site-aware console commands and docent:check site rules"`

---

### Task 11: Two-site isolation feature suite (spec §8 matrix)

**Files:**
- Create: `tests/MultiSiteTestCase.php`, `tests/MultiSiteDomainTestCase.php`, `tests/MultiSite/TwoSiteIsolationTest.php`, `tests/MultiSiteDomains/DomainIsolationTest.php`, second fixture corpus `tests/fixtures/admin-docs/index.md` + `tests/fixtures/admin-docs/internal/runbook.md`
- Modify: `tests/Pest.php`, `phpunit.xml` (map both pre-boot configurations and include them in the default suite), `src/DocentServiceProvider.php` (select route metadata before controller construction)

**Interfaces:** Consumes everything above; produces no new API — this is the spec's acceptance suite.

- [x] **Step 1: Create the fixture corpus** (`admin-docs/index.md` front matter `title: Admin Home`; `internal/runbook.md` front matter `title: Runbook`).

- [x] **Step 2: Configure both sites in `MultiSiteTestCase::defineEnvironment()` before provider boot**: `public` at prefix `help` using the existing `fixtures/docs` corpus with `middleware: ['web']`, and `admin` at prefix `admin/docs` using `fixtures/admin-docs` with `middleware: ['web', 'auth']`. Add a second test configuration with distinct domains so route generation and host matching are covered. Runtime `beforeEach()` may set values consumed during requests, but must not add routes.

```php
it('serves each site its own corpus at its own prefix', function () {
    $this->get('/help')->assertOk()->assertSee('Fixture');       // adjust to real fixture title
    $this->get('/admin/docs')->assertRedirect();                  // guest hits auth middleware
    $this->actingAs($this->adminUser())->get('/admin/docs')->assertOk()->assertSee('Admin Home');
});

it('never leaks one site\'s pages into the other\'s search', function () { /* hit /help/_search?q=runbook → no hits; authed /admin/docs/_search?q=runbook → hit */ });

it('registers keyed route names for both sites', function () {
    expect(route('docent.public.home', absolute: false))->toBe('/help')
        ->and(route('docent.admin.show', ['slug' => 'internal/runbook'], false))->toBe('/admin/docs/internal/runbook');
});

it('honors each site domain without cross-matching hosts', function () { /* configure before boot; request each host and assert the other host cannot reach it */ });

it('gates each admin panel with its own site gate', function () { /* enable database+admin on both; define two gates; assert cross-denial */ });

it('keeps llms.txt corpora separate', function () { /* /help/llms.txt lacks Runbook; authed /admin/docs/llms.txt has it */ });

it('brands each site from its own theme override', function () { /* set sites.admin.theme.accent, assert both pages' <style> blocks differ */ });
```

Write each `/* … */` out fully — they are one- or two-assertion bodies following the exact patterns used elsewhere in `tests/Feature` (grep `_search` and `llms` tests for the request/assertion idioms; reuse `adminUser()` from `TestCase`).

- [x] **Step 3: Run** — PASS (547 tests, 2,111 assertions; PHPStan clean). The suite exposed and fixed a real integration bug: route middleware ran after Laravel had already constructed controllers with the default site's scoped dependencies, so Docent now selects the site's route metadata on `RouteMatched` before controller construction.

- [x] **Step 4: Commit** — `git commit -m "fix: isolate site dependencies during route dispatch"`

---

### Task 12: Documentation and changelog

**Files:**
- Modify: `README.md` (configuration + registration sections), `CHANGELOG.md`, `workbench/resources/docs` install docs if they document config keys (grep for `docent.route.prefix` and `Docent::value` across the package's own docs pages)

- [ ] **Step 1:** Document: the `sites` config shape with the public/admin example from the spec; the cascade rule and the site-only key list; `Docent::site('x')` and global-vs-scoped registration with `$context->site`; keyed route names (`docent.{key}.*`) with the 0.1.0 breaking note; the widget `site` attribute; `--site` console options; the `(site, slug)` uniqueness rule.
- [ ] **Step 2:** `CHANGELOG.md` under Unreleased — Breaking: config restructured into `sites`, route names now `docent.{key}.*`, `docent_*` tables gain a `site` column (re-run migrations; pre-1.0). Added: multi-site support (one line per Task 5–10 feature).
- [ ] **Step 3:** `composer lint && composer analyse && composer test`, then commit — `git commit -m "docs: multi-site configuration and registration guide"`

---

## Self-review (done at plan time)

- **Spec coverage:** config cascade → T1/T4; manager pattern + current-site binding → T5/T6; keyed routes + per-site admin/gates → T6/T11; closures + `$context->site` → T2/T5/T8; DB `site` column + `(site, slug)` unique → T7; widget → T9; console + check rules → T10; §8 acceptance matrix → T11; docs → T12.
- **Type consistency:** `SiteConfig::get()`, `SiteRegistry::site(?string)`, `DocentManager::key()/config()/route()/routeName()/siteRef()`, `DocentPage::write(..., string $site = 'docs')`, `DatabaseRepository(?string $connection, string $site)` used consistently across tasks.
- **Known judgment calls for the implementer:** exact fixture titles in T11 assertions must be read from `tests/fixtures/docs`; the `DocsChecker` composition point in T10 and the pages-table column layout in T7 must be read from the current files before editing (line references in this plan date from commit `fd446aa`).
