# Multi-site Docent — design

**Date:** 2026-07-17
**Status:** Approved design, pre-implementation
**Motivating use case:** one application serving `/help` (public-facing docs, public branding, no auth) and `/admin/docs` (administrative docs behind `auth` middleware and a separate gate), from one Docent install.

## Goals

- Multiple independent documentation sites in one Laravel application, each identified by a config key (e.g. `public`, `admin`). Ship one generic `docs` site out of the box; keys are host-defined and renameable.
- Per site: route prefix/domain/middleware, content path, name/description, branding (theme, logos, fonts), navigation, layouts, feature toggles (search, AI, insights, widget, admin), and admin panel with its own gate.
- Integration closures (conditions, values, links, components, audiences, suggestions) registrable globally for all sites or scoped to one site; every closure receives context identifying the current site.
- Each site's admin panel lives at `{prefix}/admin` (path configurable per site), exactly as today.

## Non-goals (deliberately out of scope)

- Cross-site page links or navigation.
- Two sites sharing one content corpus with different branding (site ↔ corpus is 1:1).
- Per-site database tables. Sites share tables, scoped by a `site` column.

These are all addable later without unwinding this design.

## Decisions made

1. **Config shape:** one `config/docent.php` with shared settings at the top level acting as cross-site defaults, plus a `sites` array of per-site entries that override them, and a `default` key naming the default site.
2. **Route names:** always site-keyed — `docent.{key}.*` for every site including the shipped default (`docent.docs.home`, `docent.docs.show`, …). No bare `docent.*` names remain. Breaking rename is acceptable at 0.1.0.
3. **Database scoping:** a `site` string column (default `'docs'`) on shared tables, not separate tables or connections per site.

## 1. Configuration

```php
return [
    'default' => 'docs',

    // Shared defaults — overridable inside any site entry
    'theme' => [...], 'search' => [...], 'ai' => [...], 'insights' => [...],
    'content' => [...], 'database' => [...], 'cache' => [...],
    'authorization' => [...], 'widget' => [...],

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

- Resolution lives in a `SiteConfig` value object with a three-level cascade: **site entry → top-level shared value → shipped package default**.
- Site-only keys that never cascade from the top level: `name`, `description`, `route`, `filesystem`, `admin`, `navigation`, `layouts`.
- Every raw `config('docent.*')` read in the package (~89 call sites across ~30 files) is replaced by a read through the site's `SiteConfig`. No package code reads `docent.*` globals after this change — that is the enforcement mechanism as much as the refactor.
- **Filesystem path default:** the `docs` site keeps today's behavior (`null` → `resource_path('docs')`). Every other site key must set `filesystem.path` explicitly; `docent:check` reports a missing path as an error.

## 2. Sites and the manager (Laravel manager pattern)

- A `SiteRegistry` singleton lazily builds one `Site` per config key. Each `Site` owns its own `DocentManager`, `DocumentationRepository` (composite/database/filesystem as configured), `NavigationBuilder`, `SearchIndexer`/`SearchEngine`, and `DocentCache` with the site key folded into the cache prefix.
- `Docent::site('admin')` returns that site's `DocentManager` — same shape as `Cache::store()` / `Auth::guard()`. `Docent::site('unknown')` throws `InvalidArgumentException`.
- Existing container bindings (`DocentManager::class`, `DocumentationRepository::class`, `SearchEngine::class`, …) become scoped resolutions of the **current site**, so controllers, views, and the widget keep their current injection points unchanged.
- **Current-site resolution:** each site's route group sets its site key via route defaults, and a lightweight middleware binds that site as the scoped current site. Outside HTTP (console, queue), the current site falls back to `docent.default`.

## 3. Routes and admin panels

- Route registration loops over `sites`. Each site gets its own group (prefix, domain, middleware from its config); all route names are `docent.{key}.*`.
- Feature toggles (search, AI, insights, widget, admin) are evaluated per site, so features can differ between sites.
- The admin panel keeps its current structure: registered inside each site's group at `{admin.path}` (default `admin`), guarded by `can:{that site's gate}`. Separate gates per site come free from per-site config. All admin API endpoints (tree, meta, pickers, uploads, insights) operate on the current site's data via the scoped bindings.
- `llms.txt` / `llms-full.txt` are automatically per-site (they live inside each site's group), keeping each corpus's agent surface isolated.
- `docent:check` warns when two sites' effective route prefixes overlap.

## 4. Integration closures and the registry

Two registration styles, one precedence rule — site-scoped wins over global:

```php
Docent::value('planName', fn ($context) => ...);                  // all sites
Docent::site('admin')->value('planName', fn ($context) => ...);   // admin site only
```

- The global `IntegrationRegistry` remains. Each site gets a thin overlay registry that checks site-scoped entries first and falls back to the global registry. This applies uniformly to conditions, values, links, components, audiences, and `suggest()`.
- `DocumentationContext` gains a `site` property (a small read-only object exposing at least `key` and `name`), so a single global closure can branch per site. The context already flows through every closure, the renderer, navigation filtering, and search — this is one new constructor argument, not a new mechanism.
- Admin pickers (`pickerMeta()`) describe the current site's effective registry (site overlay merged over global).

## 5. Database

- Add a `site` string column, default `'docs'`, indexed, to `docent_pages`, `docent_insight_events`, and `docent_ai_questions`. The unique index on `docent_pages.slug` becomes unique on `(site, slug)`. `docent_page_revisions` is scoped transitively through its page foreign key.
- All model queries are scoped by the current site (repository and recorder layers pass the site key; no global scope magic required beyond that).
- `database.enabled` / `database.connection` cascade like other shared config: sites share tables on one connection by default, but a site may point at another connection.

## 6. Widget, console, misc

- **Widget:** `<x-docent::widget site="public" />`; omitting `site` uses the default site. Multiple widgets for different sites on one host page are legal — each iframe targets its own site's routes.
- **Console:** `docent:clear`, `docent:check`, `docent:prune-insights` gain `--site=` and default to all sites. `docent:install` scaffolds the default site. `php artisan about` lists one summary line per site.
- **Uploads:** `admin.disk` and the `_uploads` serving route are per site (they already live in site config / site routes).

## 7. Error handling

- Unknown site key in `Docent::site()`: `InvalidArgumentException`.
- Missing `filesystem.path` on a non-`docs` site: `docent:check` error.
- Overlapping route prefixes between sites: `docent:check` warning.
- Everything else keeps current behavior — failures bubble; no silent fallbacks.

## 8. Testing

- The existing suite should pass with minimal churn once the shipped single-`docs`-site config behaves identically to today (route names being the one deliberate break).
- New feature coverage using a two-site workbench setup (`public` + `admin`):
  - route and route-name isolation; per-site middleware and domain
  - per-site admin gates (public panel denied where admin panel allowed, and vice versa)
  - global vs site-scoped closure registration and precedence; `$context->site` correctness
  - per-site search/cache isolation (`docent:clear --site` bumps only one site)
  - `(site, slug)` uniqueness — same slug legal on two sites, collision within one site rejected
  - widget `site` attribute targeting
