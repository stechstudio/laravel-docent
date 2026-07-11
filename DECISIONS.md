# Decision Log

Running log of judgment calls made while building v1 overnight. Newest at the bottom.
Everything here is open for review/reversal — flag anything you disagree with.

## Confirmed by Joseph (before bed)

- Laravel 12 + 13, PHP 8.3+
- Blade + prebuilt Tailwind + Alpine (zero build step in host app)
- Create GitHub repo `stechstudio/laravel-docent` and push as we go
- Full v1 scope from handoff §20
- Maximum autonomy overnight; decisions logged here
- UI: Mintlify-caliber, gorgeous and fast

## Made by Claude (tech lead)

1. **Markdown parser: `league/commonmark` 2.x** with custom extensions. The de-facto standard,
   extensible block/inline parser API, already powers `Str::markdown()`.
2. **Directive syntax only for v1** (`:::can`, `{{ value:x }}`); HTML-component syntax deferred —
   except `<docs-component />` which is HTML-style because it's a void element and reads better.
3. **Callouts share the directive syntax** (`:::note`, `:::warning` …) — one syntax to learn.
4. **Own AST, fully decoupled from commonmark.** The commonmark AST never escapes the parser.
   Costs conversion code now, buys us Tiptap/database parsers later without touching renderers.
5. **`route:` supported alongside registered `link:`** — direct named-route references are cheap,
   high-value, and `docent:check` validates them statically.
6. **Denied page authorization → 404 by default** (don't reveal existence), configurable.
7. **Search: no external infrastructure.** Package-built index (cache-store persisted), server-side
   endpoint, page-level authorization filtering, conditional blocks excluded from the index
   entirely. Nothing restricted ever reaches the browser.
8. **Raw HTML in repo-authored markdown allowed by default** (config-gated). Repo content is app
   code reviewed in PRs; blocking HTML there hurts more than it protects. Database-authored
   content (v2) will default the other way.
9. **AST cache keyed by content hash** — no invalidation bugs possible; `docent:clear` exists anyway.
10. **Unit tests run without Laravel** (pure AST/parser/renderer), feature tests on Testbench.
11. **Pest 4, Laravel 13.19 + Testbench 11 resolved** for dev; package constraints stay ^12|^13.
12. **Versioned docs (v1/v2 dirs), localization, redirects-behavior, audiences-in-search deferred**
    per handoff roadmap. Front matter parses `redirect` but v1 may not act on it.
13. **GitHub repo created private** — flip to public when you're ready to launch.

## Build-log notes (overnight)

14. **M1 review caught a parser bug** (fixed by me): the executor's whitespace-sentinel trick — which
    lets `{{ link:x }}` tokens survive CommonMark's link-destination parsing — leaked invisible
    `\x1F` bytes into code blocks, inline code, and front matter. Fixed with a `restore()` pass at
    every literal capture point + regression tests. Docs that document Docent's own syntax now
    round-trip verbatim.
15. **Parser executor's judgment calls, reviewed and accepted**: `arguments="a,b"` attribute syntax
    for gate/condition args; `:::when advanced-exports` bare-shorthand supported; missing-integration
    markers only emit an HTML comment in debug mode.
16. **Manager/nav executor's judgment calls, reviewed and accepted**: navigation returns one flat
    ordered list of items+groups; store-agnostic cache invalidation via a version-stamp key
    (`docent:clear` bumps it); AST cached via Laravel cache serialization keyed by content hash.
17. **Workbench demo app is "Acme Ledger"**, a fictional SaaS with admin/member demo users
    (`/demo/login/admin`, `/demo/login/member`, `/demo/logout`) so authorization is demoable in a
    browser. `composer serve` boots it; docs live at `/docs`.
18. **`docent:check` earned its keep immediately**: it flagged the workbench docs' relative links,
    exposing that renderer and checker had no shared notion of link resolution. Fixed with
    `Support\InternalLink` — file-path semantics (relative to the current page's directory,
    `/docs/...`-rooted absolute, everything else external), used identically by both.
19. **UI milestone review findings (fixed by me in browser verification)**:
    - Phiki code lines rendered side-by-side (CSS `display: inline-block` on `.line` swallowed the
      newline break opportunity) — lines are now plain inline.
    - The "On this page" TOC ignored viewer context. Now context-aware: headings inside
      `:::can`/`:::when`/`:::audience` blocks appear only for viewers who'd see the block
      (regression-tested); with no context, conditional subtrees are skipped (leak-safe default).
    - Task-list checkbox alignment; missing aria-label on the search trigger.
20. **Code blocks use github-light/github-dark dual themes** (not always-dark like Mintlify) —
    the executor shipped true dual-theme and it looks great, so I kept it over my original brief.
21. **UI verified in a real browser** (dev-browser/Chromium): light+dark, ⌘K palette (ranking,
    marks, Enter-to-navigate, Esc), permission-filtered sidebar for guest vs admin, Phiki
    highlighting both themes, mobile slide-over nav, prev/next. Screenshots in ~/.dev-browser/tmp.
22. **`package-lock.json` now tracked** (reproducible asset builds); `workbench/storage` ignored
    (testbench build artifact).
## Post-v1 build log

- **Theming tokens shipped**: logo/logo_dark/logomark/favicon, font stacks + optional webfont href,
  gray palette (slate/zinc/stone/neutral), radius (sharp/default/soft) — all runtime CSS-variable
  remaps (Tailwind v4 utilities reference theme vars, so palette switching is a pure `:root`
  override). Executor also fixed a latent cascade bug: the dynamic theme block now loads after the
  stylesheet so config always wins.
- **Landing + cards shipped**: `layout: landing` front matter (hero, CTAs, no sidebar), CardGroup/
  Card AST nodes via `::::cards` fences. Directive closing matches fences by length,
  CommonMark-fenced-code style: a closing fence finalizes the nearest open directive with the
  same opening length (so `::::` closes the group even past an unclosed inner card), falling
  back to the innermost open directive when nothing matches exactly — plain `:::` everywhere
  still just works. Cards resolve hrefs through InternalLink, respect gate
  wrapping in rendering/search/TOC, and docent:check validates hrefs, hero CTAs, and icon names
  (new `unknown-icon` check; built-in inline-SVG icon set in Support\Icon).
- The landing/cards executor was killed twice by infra interruptions mid-task; I completed the
  final ~15% (dist rebuild, test suite, verification) directly.
- **Admin Phase A shipped**: opt-in migrations (`docent-migrations` tag, `docent:install
  --with-database`), `DocentPage`/`DocentPageRevision` models (model-as-API: `write()` upserts with
  revision-on-change, `publish`/`unpublish`/`revertTo`), `DatabaseRepository` serving published
  revisions only, `CompositeRepository` (DB over files, first-match-wins) with `shadowed()` +
  a `shadowed-page` docent:check warning for drift visibility. Workbench seeds a live
  "Announcements" DB page.
- **Review caught a page-level authorization hole in the DB store**: the `front_matter` column
  never reached the parsed document, so a DB page's `authorize` gated search/nav but NOT the
  rendered page itself (and the title column was ignored at render time). Fixed by having the
  repository compose the file-equivalent markdown (YAML front matter block + content) so both
  stores flow through the pipeline identically; the source hash covers the composed document, so
  metadata edits invalidate caches. Regression-tested over HTTP (guest 404 / admin 200).
- **directoryHash semantics clarified in tests**: the hash must change whenever *served* content
  changes; lifecycle states that serve identical content (never-published vs unpublished, deleted
  vs empty) may share a hash. The executor's original test demanded universal uniqueness — over-
  strict; rewritten to assert real invalidation incl. republish-newer-revision.

## Post-v1 decisions (confirmed by Joseph, July 11)

- **Admin UI stack: dependency-free Blade + Alpine + JSON endpoints** — consistent with the reader
  UI, no dependency fights with host apps. Admin assets are separate prebuilt bundles.
- **Editor: straight to Tiptap** (no markdown-editor interim). Tiptap JSON is the canonical format
  for editor-authored DB pages; the AST bridges import (markdown → AST → Tiptap) and export
  (AST → normalized markdown). Phase B ships a scaffolding textarea so the DB store is usable
  while the Tiptap build lands.
- **Database store is opt-in** (`docent:install --with-database`); repository-first stays the
  default story. Composite resolution: database overrides filesystem, drift always visible.
- **Build order**: theming tokens → landing/cards → DB+composite store → admin panel → Tiptap.
- Full detail in ROADMAP.md.

23. **Future-store audit (per Joseph's request)**: two filesystem assumptions removed so a database
    or composite repository has no blockers. `DocumentSource` now carries `format` (parser dispatch
    point — v1 registers only markdown; AST cache keys include it) and `baseDir` (relative-link
    base, decided by the repository instead of sniffed from the file path in `Page`). Everything
    else was already store-agnostic: the AST is the canonical model, renderers/search/nav/check
    consume repository + AST only, and the `DocumentationRepository` interface (find/all/partial/
    groupMeta/directoryHash) composes naturally into a first-match-wins composite. The interface
    stays marked internal/experimental so v2 can still reshape it.
