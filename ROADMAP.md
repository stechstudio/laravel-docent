# Docent — Post-v1 Roadmap

Decisions locked with Joseph (July 11, 2026). Build order: Theming → Landing → Admin A → Admin B → Admin C.

## 1. Theming tokens (~1 day)

More design tokens through the existing CSS-variable pipeline — no rebuilds, no theme engine.

- `theme.logo` + `theme.logo_dark` (path or URL) and optional square `theme.logomark`; wordmark fallback stays.
- `theme.font.sans` / `theme.font.mono` stacks → `--docent-font-sans/mono`; optional `theme.font.href`
  emits a stylesheet link for hosts that want a webfont (default remains zero external requests).
- `theme.gray`: base palette selection (slate | zinc | stone | neutral) — swaps the whole UI temperature.
- `theme.radius`: sharp | default | soft.
- Favicon/meta passthrough.

Acceptance: two installs with different tokens read as different products; all combinations legible in
light + dark; assets unchanged (tokens are runtime CSS vars).

## 2. Landing layout + cards directive (~1–2 days)

Content-driven, per-page opt-in — never config.

- `layout: landing` in `index.md` front matter: no sidebar, centered hero (title, description,
  optional `hero.cta` buttons from front matter), full-width prose below. Landing page excluded from
  prev/next; top bar keeps search + theme toggle.
- New AST nodes `CardGroup` / `Card` authored as nested directives, usable on ANY page:

  ```markdown
  ::::cards
  :::card title="Getting Started" icon="rocket" href="getting-started"
  Install, configure, and record your first transaction.
  :::
  ::::
  ```

  Cards flow through the normal pipeline: renderable, searchable (their text), and gateable
  (wrap a card in `:::can`). `href` uses the same InternalLink resolution as markdown links,
  validated by `docent:check`. Icon set: small built-in inline-SVG set (name-keyed).
- Default (non-landing) behavior unchanged: index.md renders as a normal first page with full nav.

## 3. Admin Phase A — database + composite store

Opt-in: `php artisan docent:install --with-database` publishes migrations (or
`vendor:publish --tag=docent-migrations`). Repository-first remains the default story.

- Tables: `docent_pages` (slug, title, front-matter JSON, content TEXT, `format`
  [markdown|tiptap], draft/published state, published revision pointer, author, timestamps,
  soft deletes) and `docent_page_revisions` (page FK, content, format, front matter, author,
  created_at).
- `DatabaseRepository implements DocumentationRepository` — the seam is ready: `format` and
  `baseDir` live on `DocumentSource`; `directoryHash()` equivalent = max(updated_at) + count.
- `CompositeRepository`: ordered children, first-match-wins `find()`, merged `all()` (first wins
  per slug), cascading `partial()`/`groupMeta()`, combined version hash. Default order: database
  over filesystem.
- Drift visibility from day one: when a DB page shadows a file, surface it (admin badge + diff +
  "revert to file"). Never silently.
- Search/nav/TOC/check work unchanged (they consume the repository interface). `docent:check`
  gains awareness of the composite (reports which store a page came from).

## 4. Admin Phase B — the panel

`/docs/admin` (configurable), gated by `config('docent.admin.gate')` (default: a
`viewDocentAdmin` gate the host defines). Stack: Blade + Alpine + JSON endpoints — the package
stays dependency-free; admin JS/CSS are separate prebuilt bundles (`docent-admin.js/css`) so the
reader bundle stays lean.

- Page tree matching navigation grouping: create / rename / move / delete (DB pages only; file
  pages shown read-only with "override" action that copies them into the DB store).
- Front matter as form fields: title, description, group/order, hidden, search.exclude, and an
  `authorize`/`audience` picker fed by registry metadata + `Gate::abilities()`.
- Draft → preview → publish workflow; revision history with diff + restore.
- Preview-as: render preview with the admin's own context in v1 of the panel; audience/user
  impersonation preview is a fast-follow (needs careful authorization design).
- Image upload endpoint → configurable disk (`docent.admin.disk`), served/linked properly.
- `docent:check` inline: on save, run the reference checks for that page (unknown integrations,
  broken links, missing includes) and surface warnings in the UI.
- All writes POST/PUT/DELETE through the gate + CSRF; audit fields on every mutation.

## 5. Admin Phase C — Tiptap editor (decided: straight to Tiptap, no markdown-editor interim)

The Docent AST is the bridge; Tiptap JSON is just another authoring format.

- **Canonical format for DB pages authored in the editor: Tiptap JSON** (`format: 'tiptap'`),
  parsed by a new `TiptapDocumentParser` into the same AST. Renderers/search/check untouched.
- **Import**: existing markdown (file pages being overridden, or pasted markdown) converts
  markdown → Docent AST → Tiptap JSON via an `AstToTiptap` serializer.
- **Export**: AST → normalized markdown (`MarkdownExporter`) powers "export to file" / future
  PR-creation workflows. Per the handoff: semantic round-trip promised, never byte-for-byte.
- Custom nodes mirroring the directive set: gate block (can/cannot), condition block
  (when/unless), audience block, callouts, include, component embed, inline value chip,
  app-link/route marks, code block with language + title. Each renders as a labeled visual
  container in the editor ("Shown when: billing.manage").
- Node pickers driven by a registry metadata endpoint (name, label, description — already
  captured by `Registered*` records). Unknown-reference warnings inline, same checks as save.
- Editor bundle: Tiptap (MIT, npm) bundled into `docent-admin.js` at package build time — hosts
  still install nothing.
- Honest scope note: this is the long pole. Estimate 3–5× Phase B. The panel (Phase B) should
  ship with a minimal textarea + preview as a stopgap so the DB store is usable while the Tiptap
  work lands; that textarea is not a product surface, just scaffolding, and `format: 'markdown'`
  DB pages remain fully supported forever.
- **Serialization contract (decided with Joseph)**: the editor schema is CLOSED and exactly
  co-extensive with the markdown dialect — every node has a canonical markdown spelling, the AST
  is always the pivot (never Tiptap ↔ markdown directly). Round-trip is semantic, not byte-level;
  exports are normalized markdown and exporting is a fixpoint. Raw HTML is not authorable in the
  editor; imported HTML blocks are carried as opaque read-only widgets and export verbatim.
  **No interactive source mode** — a read-only "View markdown" / copy-export only (the jumpy
  normalize-on-flip UX isn't worth it; the repo is the power-user surface). Full node schema:
  DESIGN.md §Tiptap schema contract.

## Standing constraints

- Authorization-before-convenience applies to every admin surface (tree, previews, uploads).
- DB-authored content: `allow_html` defaults OFF (unlike repo-authored files).
- Reader UI bundle stays lean; admin assets load only on admin routes.
- Repository interface stays internal/experimental until the composite + database stores prove it;
  then it stabilizes as the public extension point.
