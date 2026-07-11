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

- **Admin Phase B shipped** (B1 backend + B2 panel): 14 gated JSON endpoints (tree across both
  stores incl. drafts, CRUD, publish/unpublish/revert, file-page override, revisions, live preview
  through the real render pipeline with inline reference-check issues, uploads, registry metadata
  for pickers) and a three-pane Alpine panel matching the reader's design language — page tree
  with store/status chips, editor with directive-insert toolbar + front matter panel, debounced
  live preview, revision slide-over, dark mode. Separate prebuilt bundles
  (docent-admin.css/js, ~54/58KB) so reader pages load none of it. Browser-verified end to end:
  guest/member 403, tree loads, DB page edit → live preview → save draft → publish → live.
- **The B2 executor was cut off by the account's monthly API spend limit** ~85% through; I
  finished the remainder (revisions slide-over, admin build scripts, asset allowlist, panel tests)
  in the main session. Further subagent delegation is unavailable until the limit resets/raises.

- **Phase C shipped (Tiptap)**: C1 format bridge — TiptapDocumentParser, AstToTiptap, normalized
  MarkdownExporter (fixpoint-verified across 21 real docs), format-aware admin API + markdown
  export endpoint. C2 editor — fully WYSIWYG with custom node views (gate/condition/audience
  frames with pickers, callouts, cards, value/app-link chips, include/component widgets, opaque
  HTML blocks), slash menu, bubble menu, View Markdown modal. Admin bundle 429KB min (tables kept
  for contract correctness over the 400KB target). Both executors' work review-verified; my pass
  caught Laravel's TrimStrings middleware eating meaningful whitespace inside rich-text nodes on
  save (fixed by reading tiptap payloads from the raw request body, regression-tested); the C2
  executor itself caught and fixed a setEditable event-ordering bug that blanked loaded docs.
- **Tiptap task items** use an extended listItem with a `checked` attr per our contract rather
  than Tiptap's taskList/taskItem extensions (whose node types aren't in our schema).

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

## Editor framework posture (discussed with Joseph, July 11)

- **Vanilla node views today; React is a contained, later-swappable view-layer decision.**
  Tiptap's Notion-like template / UI-components kit is React-first, but the Notion UX decomposes
  into framework-agnostic ProseMirror features (we built slash + bubble menus vanilla; drag-handle
  has a neutral core). Our durable investment — schema contract, PHP bridge, serializers, admin
  API, and the Tiptap *extensions* (names/attrs/commands) — is framework-independent; only the
  ~1.5k-line node-view/chrome layer is framework-specific, and ReactNodeViewRenderer wraps the
  same extensions if we ever swap.
- Because the admin bundle is prebuilt and self-contained, adopting React later is invisible to
  host apps — no compat or migration cost. The trade was our maintenance surface + bundle weight,
  not host compatibility.
- **Re-evaluate triggers**: collaborative editing / comments / Tiptap AI components on the
  roadmap, or a third instance of reimplementing their template chrome by hand.
- **Standing discipline**: extensions define schema and commands; node views only render.
  Document semantics never leak into view code — that keeps the swap cheap.

## Admin panel UX overhaul (after Joseph's review, July 11)

- Joseph's critique of the first admin panel: wrapping tree badges, cramped equal-split editor,
  Write/Preview visual inconsistency, confusing "Override into database", dead hamburger. All
  addressed in a hands-on redesign with browser iteration (his feedback: delegation without
  browser-in-the-loop produced a half-baked surface; the reader UI got iteration and it showed).
- New layout: Write/Preview as tabs (editor gets full document width, ~46rem centered prose);
  page settings moved to a right rail (status, metadata, access/permissions with explanatory
  copy, tools, danger zone); tree rows are single-line always (store glyph + truncating title +
  status dot: amber draft / blue unpublished edits / red shadowing); the top-left toggle now
  collapses the tree at any width (overlay below md).
- Write/Preview parity: the callout and code-block node views now render the READER's exact
  markup/classes (docent-callout, docent-code card with header) with hover-revealed edit
  affordances; editing chrome (gate/condition/audience frames, chips, widgets) stays editorial
  by design since it has no reader equivalent.
- "Override into database" became "Edit this page" with an explanatory confirm ("creates a
  database copy readers see instead of the file; discard anytime"); shadowing shows as a slim
  amber banner with inline discard.
- Read-only pages hide all node-view edit affordances (programmatic commands would otherwise
  bypass editable:false).
- Preview renders on tab activation (stale-tracked) instead of debounced on every keystroke —
  fewer requests, and the tab is the natural "check my work" moment.

## Feedback round 2 (Joseph, July 11 evening)

- **Admin panel moved to `/docs/admin`** (was `/docs/_admin`), configurable via
  `docent.admin.path`. Joseph: it's what users expect. The panel registers before the reader's
  catch-all, so a docs page slugged exactly `admin` would be shadowed — documented in the config
  comment, and the path is configurable for anyone who needs that slug.
- **Home-page 404 fixed.** Root `index.md` maps to the empty-string slug, which cannot travel as
  a URL path segment — clicking "Acme Ledger" in the tree hit `GET …/api/pages/` → 404 toast.
  The empty slug now travels as the reserved `_home` wire alias (underscored slugs can never be
  real pages, so no collision); controllers map it back via `resolveSlug()`, the empty slug is
  valid for writes (you can override/edit/publish the home page), and JS guards use
  `slug === null` instead of falsy checks. Regression test covers detail → override → edit →
  publish for `_home`.
- **Accents split**: the reader's default accent is now sky-600 `#0284c7` (was indigo — Joseph
  wanted a blue variant as the default; sky-600 passes AA on white). The admin chrome wears its
  own fixed pink (pink-600 light / pink-500 dark), set on `#docent-admin` so it beats the inline
  theme style; the preview pane re-asserts the host's brand accent inline so previews stay true
  to the reader. Rationale: authors always know which surface they're on, and the admin identity
  is ours while the reader identity is the customer's.
- **Ability picker humanized**: the access datalist shows "View reports" style labels next to
  raw ability names (JS-side: split on separators/camelCase, verb-first flip for a known verb
  list). The stored value stays the technical string — front matter is truthful.
- Demo polish: removed the redundant body `# Acme Ledger` h1 from workbench index.md (reader
  hides a leading h1; the editor shows content faithfully, so the duplicate looked wrong);
  fixed `reports/_group.yml` icon `chart-bar` → `chart` (not a built-in icon name).
- Deferred pending Joseph's direction (answered with recommendations, not built): page icons in
  nav, database-side group metadata management, upload-serving strategy for private disks.

## Feedback round 3 (Joseph, July 11 late evening)

- **Pink admin accent reverted** the same evening it shipped — Joseph prefers one brand accent
  everywhere, so the admin simply inherits `docent.theme.accent` (sky-600 default) and follows
  any host re-branding automatically.
- **Image uploads fixed + hardened.** The demo failure: `Storage::url()` on the `public` disk
  yields `/storage/...`, which 404s without `storage:link` (workbench had none) — so inserted
  images were invisible. Uploads are now served through a `_uploads/{path}` streaming route
  inside the docs group: works on ANY disk (public, local, private S3 — no symlink, no public
  bucket, no unsigned URLs), inherits the docs middleware (private docs keep images private),
  hashed filenames get `immutable` cache headers. Path constrained to `docent/` + traversal
  guard. Verified in-browser end-to-end (file input → toast → rendered <img>). S3 optimization
  (redirect to temporaryUrl instead of streaming) noted as a future nicety.
- **Icons: bundled Heroicons, rejected CDNs.** Runtime CDN icon loading can't avoid
  request-per-icon flicker; irrelevant anyway since Docent renders icons server-side as inline
  SVG (zero client cost). Bundled the full Heroicons 24px outline set (324 files, MIT, license
  included) under `resources/icons/`; `Icon::svg()` reads+normalizes on demand (traversal-guarded
  name regex, per-request cache), legacy Feather names kept as fallback so existing content
  never breaks. New lazy `admin/api/icons` endpoint powers the picker without bloating page load.
- **Groups management shipped** (built by Opus executor, reviewed + browser-verified by lead):
  group metadata (label / order / icon) stored as reserved never-published `_groups/{dir}` rows
  in the existing pages table — no new migration; read from the row's front_matter so changes
  take effect immediately (no publish step); composite cascade makes the database row override
  `_group.yml`. Admin: hover pencil on tree group headers → settings modal (label, order,
  searchable icon picker, provenance note, reset-to-defaults); tree groups now sort by
  (order, label) like the reader. Reader sidebar renders group icons (finally consuming the
  `icon:` that `_group.yml` always accepted). Groups are still created implicitly by slugging
  pages into directories (`billing/refunds`) — the new-page form now hints this.
- Review findings: none blocking — executor work was clean (soft-delete/unique-slug interplay
  already handled by `DocentPage::write` `withTrashed()` upsert; verified).
