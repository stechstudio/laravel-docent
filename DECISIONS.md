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
