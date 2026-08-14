# Plan 005: Public-site SEO layer — guest-pruned sitemap.xml, canonical URLs, OG/Twitter meta, JSON-LD

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving on. If anything in "STOP
> conditions" occurs, stop and report — do not improvise. Update this plan's row in
> `plans/README.md` when done (leave `plans/` uncommitted).
>
> **Drift check (run first)**: `git diff --stat 713bcb8..HEAD -- src resources/views config`
> If in-scope files changed since planning, re-verify the excerpts below before
> proceeding.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none
- **Category**: feature
- **Planned at**: commit `713bcb8`, 2026-07-18

## Why this matters

Docent sites can be public (a marketing-adjacent docs site) as well as gated (in-app
help). For public sites, Docent currently ships no sitemap, no canonical URLs, and no
structured data — competitors (Laradocs) auto-generate all of it. Docent's twist is
that it can do this *correctly on a partially gated site*: the sitemap is built from
what a **guest** may see, so gated pages never leak into search engines — the same
permission pruning that already powers navigation, search, and llms.txt. This is a
leapfrog feature, not parity.

## Current state

- `src/DocentManager.php:234-244` — `contextFor(?Request $request)` builds the viewer
  context but falls back to `Auth::user()` even for a null request. **A sitemap must
  not use it**: it needs a true guest context regardless of who is browsing.
- `src/Content/AgentFeed.php` — `llmsText()` is the pattern to imitate: it takes
  `$this->docent->navigationSections($context)`, flattens with
  `$this->navigation->flatten(...)` (public method on `NavigationBuilder`), and caches
  through `DocentCache` keyed by `$this->repository->directoryHash()`.
- `resources/views/layout.blade.php:1-27` — the `<head>`: `<title>`, plain
  `<meta name="description">`, favicon/font links, CSS/JS. Verify what social/OG tags
  exist before adding (grep `og:` — a match was seen in this file; if a partial OG
  block exists, extend it rather than duplicating).
- Route registration: `src/DocentServiceProvider.php` registers each site's routes in
  a per-site group with `'as' => 'docent.'.$key.'.'` — find the group containing the
  `home`/`show`/`llms` GET routes and follow its style exactly.
- Config: `config/docent.php` is the shipped config; `src/Sites/SiteConfig.php`
  implements the cascade and contains the list of **site-only** sections (name, route,
  filesystem, admin, navigation, layouts…) vs shared sections. The new `seo` section
  must be SHARED (cascades from top level to all sites).
- `src/Http/Controllers/LlmsController.php` — smallest existing controller; the
  structural pattern for the new `SitemapController` (method injection, `response()`
  with content-type header).
- Tests: `tests/Feature/` has llms/navigation tests using gated fixture pages — find
  them with `grep -rln 'llms' tests/Feature` and reuse their fixture setup style.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `composer test` | all pass (555 at planning time) |
| Lint | `composer lint` | exit 0 |
| Static analysis | `composer analyse` | exit 0 |

## Scope

**In scope**:

- `src/DocentManager.php` (add `guestContext()`)
- `src/Http/Controllers/SitemapController.php` (create)
- `src/DocentServiceProvider.php` (route)
- `config/docent.php`, `src/Sites/SiteConfig.php` (new shared `seo` section)
- `resources/views/layout.blade.php` (canonical + meta), a new
  `resources/views/partials/seo.blade.php` if the head block grows past a few lines
- `tests/Feature/SeoTest.php` (create)
- `README.md` + `CHANGELOG.md` (brief feature notes)

**Out of scope**:

- The widget and admin layouts — no SEO tags there.
- `lastmod`/priority/changefreq in the sitemap — deliberately omitted (no reliable
  per-page timestamp exists for filesystem pages; do not invent one).
- BreadcrumbList JSON-LD — the breadcrumb affordance returns a display string, not
  URL-shaped data; do not restructure it. Article-level JSON-LD only.
- robots.txt — the host app owns it.
- Protected dirty files and `plans/` (see plans/README.md global rules).

## Git workflow

- Work on `main`. One commit: `feat: add sitemap, canonical URLs, and structured data for public sites`.
- **Never add Co-Authored-By or "Generated with" lines.** Do NOT push. Stage by explicit path.

## Steps

### Step 1: `guestContext()` affordance

Add to `DocentManager` (next to `contextFor`):

```php
/**
 * The context of an anonymous visitor, regardless of who is browsing —
 * public surfaces (sitemap) must never widen to the current viewer.
 */
public function guestContext(): DocumentationContext
{
    return new DocumentationContext(
        user: null,
        request: null,
        gate: static fn (string $ability, array $arguments): bool => Gate::allows($ability, $arguments),
        site: $this->siteRef(),
    );
}
```

Match `DocumentationContext`'s actual constructor signature (read it first — the gate
closure signature above must line up with how `contextFor()` builds its closure at
`DocentManager.php:239-241`, minus the user branch).

**Verify**: `composer analyse` → exit 0.

### Step 2: Config

In `config/docent.php`, add a documented shared section (match the file's comment
style):

```php
'seo' => [
    // Serve {prefix}/sitemap.xml listing the pages a guest may see.
    'sitemap' => true,
],
```

Add `'seo'` to the SHARED sections in `src/Sites/SiteConfig.php` (find how existing
shared sections like `'search'`/`'ai'` are declared and follow exactly).

**Verify**: `composer test` → all pass.

### Step 3: SitemapController + route

`src/Http/Controllers/SitemapController.php`, modeled on `LlmsController`. Behavior:

- 404 (`abort_unless`) when `$docent->config('seo.sitemap', true)` is false.
- Build the slug list from the guest context: home page (`''`) plus every item from
  `NavigationBuilder::flatten()` over `$docent->navigationSections($docent->guestContext())`
  — this automatically excludes hidden pages, redirect stubs, and anything gated from
  guests. Skip items whose `searchExcluded` is true only if llmsText does the same
  (mirror `AgentFeed::llmsFullText`'s filtering exactly; read it and copy the rule).
- Emit XML: `<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">`
  with one `<url><loc>{$docent->fullUrl($slug)}</loc></url>` per page, `e()`-escaped.
- Cache the XML string via `DocentCache` keyed by `'sitemap:'.$repository->directoryHash()`
  (the guest view can't vary by viewer, so no fingerprint needed) — follow the
  `remember` usage in `AgentFeed`.
- Respond with `Content-Type: application/xml`.

Route in `DocentServiceProvider`, inside the same per-site group as the llms routes:
`GET sitemap.xml` → `SitemapController`, route name suffix `sitemap` (full name
`docent.{key}.sitemap`).

**Verify**: `composer test`, then manually:
`php vendor/bin/testbench route:list | grep sitemap` → one route per site.

### Step 4: Head tags

In `resources/views/layout.blade.php` `<head>` (reader + landing pages only — this
layout serves both; confirm the widget uses a different layout file and leave it
alone):

- `<link rel="canonical" href="{{ $docent->fullUrl($currentSlug ?? '') }}">`
- OG: `og:title` (same computed title as `<title>`), `og:description` (when set),
  `og:type` `article`, `og:url` (canonical), `og:site_name`. Extend any existing OG
  block instead of duplicating.
- `<meta name="twitter:card" content="summary">`
- JSON-LD `<script type="application/ld+json">` with
  `{"@context":"https://schema.org","@type":"TechArticle","headline":<title>,"description":<description>,"url":<canonical>,"inLanguage":<app locale>}` —
  build the array in PHP and `@json` it; never hand-concatenate JSON.

If this grows past ~15 lines, extract to `resources/views/partials/seo.blade.php`
and `@include` it.

**Verify**: `composer test` → all pass (existing browser/layout snapshots must not
break; if a Playwright test asserts head contents and fails, that is expected only if
it asserts the ABSENCE of these tags — otherwise STOP).

### Step 5: Tests

`tests/Feature/SeoTest.php` (Pest, model after the llms feature tests):

1. Sitemap lists the guest-visible pages of the default site with full URLs.
2. A page gated by an ability (use the existing gated-fixture pattern) is absent from
   the sitemap **even when the request is made by an authorized user** — proves the
   guest context, the core behavior.
3. Hidden pages and redirect stubs are absent.
4. `seo.sitemap => false` → sitemap URL returns 404.
5. Multi-site: each site's sitemap contains only its own pages under its own prefix
   (model after `tests/MultiSite/` setup).
6. A page response contains the canonical link, `og:title`, and a JSON-LD block that
   `json_decode`s with `@type === 'TechArticle'`.

**Verify**: `composer test` → all pass including 6+ new. `composer lint`,
`composer analyse` → exit 0.

### Step 6: Docs stubs + commit

One short paragraph in `README.md` (near the llms/agent section) and a `CHANGELOG.md`
entry. Commit.

**Verify**: `git show --stat HEAD` → only in-scope files.

## Done criteria

- [ ] `composer test` / `lint` / `analyse` all exit 0; new SeoTest passes
- [ ] Gated page provably absent from sitemap under an authorized session (test 2)
- [ ] `git status` clean outside in-scope files; `plans/README.md` row updated

## STOP conditions

- `DocumentationContext`'s constructor doesn't accept the guest shape in step 1.
- An existing test asserts head contents that conflict with the new tags.
- The llms filtering rule you're told to mirror in step 3 turns out to be per-viewer
  in a way a guest sitemap can't reuse.

## Maintenance notes

- If per-page timestamps ever land (DB pages have them; files don't), `lastmod` can be
  added — keep it out until filesystem pages have a truthful source.
- Reviewer: check the sitemap cache key ignores the viewer entirely and that
  `guestContext()` is used nowhere a real viewer context belongs.
