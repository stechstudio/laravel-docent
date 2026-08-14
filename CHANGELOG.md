# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Share links: one documentation page, readable by someone who is not signed in. Gated docs create friction at the moment an answer matters most — a support reply links to the page that explains the problem, and the recipient hits a login screen; a sales conversation turns on one feature nobody outside the account can read. A viewer who passes `share.gate` can now copy a link from the reader's top bar, and the URL is the page's own plus a fourteen-character `?s=` token. Off by default; enable with `share.enabled` and define the gate.

  Nothing viewer-specific travels with the link. Authorization blocks resolve as they would for a logged-out visitor, `{{ value: }}` and `{{ link: }}` tokens render their registered label, and no host resolver, condition, audience predicate, or component runs at all — so a share link cannot carry account data, and a resolver written for a signed-in viewer cannot fail on one. A recipient who *is* signed in gets their normal page in their own context instead, because a link is often sent precisely so someone can see something as themselves; the token is inert for them.

  The token is an alternative credential rather than a hole in the guard. Nothing is ungated: it satisfies the host's guard for the page and for that page's images and stylesheet, each signed for its own path, and every other route — search, the assistant, insights, the widget, `llms.txt`, the sitemap, the admin panel — refuses it however valid it is. Links inside a shared page still lead to the login wall, which is the intended nudge, and the page carries `noindex, nofollow` and never appears in the sitemap. Requests presenting a token that fails to verify are rate limited; verified ones are not, so a page full of images still loads.

  Hosts add no middleware and change no existing config. The service provider registers the credential into the kernel's middleware priority map ahead of `AuthenticatesRequests`, so it sorts after the session starts and before the guard wherever `auth` was written. A bespoke guard implementing neither that contract nor `Authenticate` can be named in `share.before`. Changing `share.salt` invalidates every outstanding link at once.

## [1.3.1] - 2026-08-13

### Fixed

- A framed image's click-to-enlarge overlay now resolves its source the same way the inline image does. `:::frame` emits the image twice — once in the page, once inside the lightbox — and only the inline copy was rewritten onto the docs image route, so the enlarged copy kept the raw Markdown source. The browser resolved that relative path against the page URL, which is a Docent route rather than a directory, and the overlay opened onto a broken image on every framed screenshot. The page itself looked correct, so nothing surfaced the failure until a reader clicked; `docent:check` stayed silent because the Markdown reference it validates is the one that already worked.

## [1.3.0] - 2026-08-05

### Added

- New `token-in-code` check. Token syntax inside a code span renders verbatim by design — it is how the dialect documents itself — which also means such a token never becomes an AST node for the reference checks to inspect. A page could ship `` `{{ value:account.plan }}` `` where a real value belonged, render 200, and pass every other check. The new check reads inline code literals directly and warns when the token names a value, link, or route this application actually resolves. Generic examples naming nothing registered stay silent, and fenced code blocks are out of scope: a block showing what to write is supposed to contain literal dialect syntax. Silence it with `'token-in-code' => 'off'` in `docent.check.rules`.

- Applications can declare their ability surface for `docent:check` and the admin's `authorize:` completion, instead of relying on `Gate::has()`. `Gate::has()` only sees abilities passed to `Gate::define()`, so an application that bridges permissions through a single `Gate::before` callback defines no gates and had every `authorize:` key in its content reported as unknown. Set `check.abilities` to a list of strings or a backed-enum class-string, or register a closure with `Docent::abilities(...)` from a service provider when the list is dynamic. Unset, the behavior is unchanged: `Gate::has()` remains the fallback.

- Page enumeration and a whole-tree smoke assertion in the testing helpers. `$this->docs()->pages()` returns every content page slug using Docent's own derivation (root `index.md` is the empty slug, `foo/index.md` collapses to `foo`, partials and redirect stubs are excluded), so suite-wide invariants no longer require reconstructing that logic with a `Finder` loop that drifts the day the conventions change. `$this->docs()->as($user)->assertAllPagesRender()` renders every page the viewer may open, continues past a failure so a broken corpus reports at once, and names every slug that broke along with its error. Pages the viewer cannot see are skipped rather than failed, but a sweep that reached none of them fails rather than passing vacuously. `DocsTester` also gained `as()` and `forAudience()`, which now scope `page()` as well as `search()`.

- New opt-in `gated-link` check, reporting links whose readers are not guaranteed to be able to open the target. `broken-link` validates that a target exists; this asks whether the readers of the linking page can open it. The failure surfaces late, since the author can see both pages and CI stays green — the only signal is a user with a narrower role reporting a link that goes nowhere. Decidable without inventing a permission lattice: each link carries the requirements its readers provably satisfy — the source page's own `authorize`/`audience`, plus any enclosing `:::can`/`:::audience` block — and a target requirement absent from that set is reported. Naming the target's own requirement in a block therefore declares the guarantee and silences the warning, while a block naming a different ability does not. `:::cannot` widens rather than narrows, and `:::when`/`:::unless` gate on conditions rather than authorization, so neither counts. Markdown links, card hrefs, hero CTAs, and links inside included partials are all covered. Enable with `'gated-link' => 'warning'` in `docent.check.rules`.

- Images stored in the documentation directory are now served, so a screenshot can live next to the page it illustrates and move with it. A page-relative `![](images/foo.png)` is rewritten onto a new `{prefix}/_images/{path}` route, which inherits the docs route group's middleware — a documentation site behind auth keeps its screenshots behind auth, and each site serves only its own content directory. PNG, JPEG, GIF, WebP, AVIF, and SVG are streamed, with `..` traversal and symlinks out of the tree refused, SVGs served under a restrictive document policy, responses marked private and revalidated so a shared cache never holds a gated site's screenshot, and conditional requests answered with a 304. `/`-rooted public paths and external URLs are unchanged. Relative images are also emitted absolute in the agent Markdown and `llms.txt` feeds, which previously left them unresolvable.

### Changed

- A registered value or link closure that throws no longer takes down the page. The token substitutes nothing, the throwable is passed to `report()` so it still reaches exception tracking, and the rest of the document renders. Previously one closure failing for one reader state — a tenant-scoped lookup with no tenant selected, a route helper with nothing bound — returned a 500 for a page whose other content was perfectly renderable, on every page that token appeared on. Set `render.strict_tokens` to true to get the exception instead.

  Only invocation of the application's own closure is covered. Instantiating a class-string resolver, converting its result to a string, and resolving a `{{ route:… }}` token all still fail loudly: those break identically for every reader, so they are defects to surface rather than session state to render around. A render that did degrade a token is never written to the agent-Markdown or `llms-full.txt` caches, since a cache key cannot see the session state that caused the failure and the missing value would otherwise be served to every later reader.

### Fixed

- `docent.check.rules` now applies to the admin editor's per-draft validation, not only to `docent:check`. A rule silenced with `'off'` was still reported on every save and preview, and a promoted severity was still shown as its original one — so the editor could contradict what CI was configured to accept.

- `missing-image` no longer confirms a relative image path that could never be served. It previously validated against the page's source directory — which nothing serves — so an image stored next to its Markdown passed the check and rendered broken in the browser: positive confirmation for a definitely-broken page. Relative paths now resolve through exactly the same logic as the serving route, so a passing check means the route will serve the file. References that climb out of the documentation directory, or name a file type Docent does not serve, are reported instead of quietly resolving, and images inside `:::include`d partials are now validated the way the renderer resolves them.
- `docent:check` now rejects one site storing its content inside another's directory. The outer site enumerates the inner site's pages as its own and would serve its images under the outer site's middleware, so the two are not isolated at all.

## [1.2.0] - 2026-08-05

### Added

- Soft navigation in the reader. Clicking between documentation pages fetches the next server-rendered page and swaps it in place instead of a full browser load: the sidebar keeps its scroll position and open groups, the Assistant panel stays open mid-conversation, and back/forward behave as expected. Anything unusual — an error response, a redirect to login, a landing page or custom layout without the docs chrome — falls back to a normal full navigation. Host applications can listen for the `docent:navigated` window event to track client-side page views.

## [1.1.0] - 2026-08-05

### Added

- The sidebar keeps its scroll position while clicking between pages. Position is held per tab (and per site and section), restored before first paint so there's no jump, and if the remembered position would hide the current page's link, the sidebar nudges it into view instead.
- Page-backed expanding sidebar groups. A nested directory with an `index.md` now renders its group header as a link to that landing page, with a separate labeled chevron that expands or collapses the group without navigating; a directory without an `index.md` keeps the toggle-only header. The mode is derived entirely from the filesystem — no new `_group.yml` keys.

### Changed

- A group's `index.md` no longer appears as an item inside its own group: nested groups promote it to the header link, and top-level groups pin it as the first entry. The landing page now always reads first within its group (prev/next order and the `llms.txt`/`llms-full.txt` feeds), regardless of its `order:` front matter.

## [1.0.2] - 2026-07-21

### Changed

- `docent:install` output now points to `docent:make` and `docent:check` (with the `--format=json` option), and the `docent:guide` reference describes `docent:make` — so the scaffolder, JSON diagnostics, and quality rules are discoverable from a cold start instead of only via `artisan list` and command help.

## [1.0.1] - 2026-07-21

### Fixed

- Starter pages scaffolded by `docent:install` no longer open with a body `#` heading. The page title already renders as the `h1`, so the starter content now matches Docent's own authoring guidance and the `docent:make` templates, and a fresh install passes the opt-in `single-h1` quality rule cleanly.

## [1.0.0] - 2026-07-21

### Security

- Filtered `javascript:` and `data:` URL schemes from Markdown link, card, and video hrefs, preventing stored XSS from database-authored pages.

### Breaking changes (pre-1.0)

- Moved `agentMarkdown`, `llmsText`, `llmsFullText`, and `discoveryLinkHeader` from `DocentManager` to `STS\Docent\Content\AgentFeed`; resolve it from the container, where it is site-scoped like the manager.
- Moved the admin authoring API (`adminTree`, `adminDetail`, `filesystemSlugLocked`, `adminGroups`, `updateGroupMeta`, `removeGroupMeta`, `overrideFromFilesystem`, `draftDocument`, `tiptapError`, `exportMarkdown`, `previewDraft`, `draftIssues`, and `pickerMeta`) from `DocentManager` to `STS\Docent\Admin\Editor`; applications calling these methods on the manager or `Docent` facade must resolve the current site's `Editor` instead.
- Restructured site identity, routing, filesystem, admin, navigation, and layout configuration under `docent.sites`, with `docent.default` selecting the fallback site.
- Renamed every route from `docent.*` to the site-keyed `docent.{key}.*` form, including the shipped `docs` site.
- Added a `site` column to the shared `docent_pages`, `docent_ai_questions`, and `docent_insight_events` tables and changed page uniqueness to `(site, slug)`. Pre-release applications should rerun the published migrations.
- Admin uploads are now stored and served under a per-site namespace (`docent/{site}/…`); previously uploaded files under the flat `docent/` directory must be moved into their site's directory (e.g. `docent/docs/`) or re-uploaded.
- The `<x-docent::search-box>` and `<x-docent::hero>` components now require a `:docent` prop (the site manager); host layouts embedding them bare must pass it, e.g. `<x-docent::search-box :docent="$docent" />`.

### Added

- `docent:make` command scaffolding a page from a Diátaxis content-type template (tutorial, how-to, reference, concept).
- `docent:check --format=json` for machine-readable diagnostics, per-rule severity overrides via `docent.check.rules`, and opt-in authoring-quality rules (`single-h1`, `description-length`).
- `docent:install` writes an idempotent Docent authoring pointer into the project's `AGENTS.md`/`CLAUDE.md` so coding agents discover `docent:guide`.
- Page lifecycle events `PageSaved`, `PagePublished`, `PageUnpublished`, and `PageDeleted` for host applications to hook.
- `COMPATIBILITY.md` documenting the public API surface covered by semantic versioning; internal collaborators are marked `@internal`.
- The four Eloquent models (`DocentPage`, `DocentPageRevision`, `AiQuestion`, `InsightEvent`) are no longer `final`, so host applications can extend them.
- Split the `docent-views` publish tag so it exposes only override-intended templates; internal partials publish under `docent-views-internal`.
- Publishable language files for translating the reader, search, Assistant, and widget interfaces.
- Configurable Assistant answer language, including the current application's locale.
- Guest-pruned per-site sitemaps plus canonical URLs, social metadata, and TechArticle JSON-LD on public reader pages.
- Social-preview images for link unfurls via a shared `seo.image` setting, overridable per page with an `image:` front matter key.
- A `docent:guide` command printing the authoring reference plus the application's sites and registered integrations, giving coding agents one entry point for writing docs; the reference also ships in the package archive.
- Multiple independent documentation sites from one installation, each with its own corpus, route prefix or domain, middleware, branding, feature switches, and admin gate.
- A lazy site registry and site-aware manager/service graphs with shared configuration defaults and explicit site-only settings.
- Global and site-scoped integration registration with site-local precedence and the current site exposed on every `DocumentationContext`.
- Site-isolated database pages, Assistant questions, insights, search indexes, caches, `llms.txt` output, uploads, and admin operations.
- Site targeting for `<x-docent::widget>` and keyed route helpers through `DocentManager`.
- `--site` selection for `docent:clear`, `docent:check`, and `docent:insights:prune`, plus whole-map definition checks for invalid sites and overlapping routes.
- In-app documentation site rendered inside the host application's runtime, with authentication, gates, and policies applied to pages, navigation, and search.
- Markdown authoring with YAML front matter, audience-conditional blocks, dynamic values, named-route app links, includes, and embedded Blade components.
- Structural directives for task-focused guides: callouts, steps, tabs, accordions, code groups, frames, and video embeds.
- Ranked full-text search with section-level results, typo tolerance, keyboard navigation, and permission-aware filtering.
- `docent:check` command validating links, values, routes, includes, images, slugs, and front matter, with `--strict` mode for CI.
- Testing helpers (`InteractsWithDocs`) for asserting documentation visibility and search results in the host app's suite.
- Browser-based admin editor for authoring and organizing documentation, with image uploads sanitized at storage time.
- Optional AI assistant answering viewer questions from the documentation corpus, with temporary conversations and an embeddable help widget.
- Privacy-conscious documentation insights: top searches, low click-through queries, and page engagement.
- Prebuilt, themeable UI with dark mode and server-side syntax highlighting; no build step required in the host app.

### Fixed

- Assistant answers now favor flat, readable Markdown with properly fenced code examples and clickable documentation citations.
