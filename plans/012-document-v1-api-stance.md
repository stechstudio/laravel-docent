# Plan 012: Document the v1 compatibility promise and annotate internal contracts

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Content/Repositories src/Validation/Check.php resources/guides/authoring.md`
> On any change, compare "Current state" to the live code first; on a mismatch,
> treat it as a STOP condition.

## Status

- **Priority**: P3
- **Effort**: S
- **Depends on**: 007–011 should land first (this doc *describes* the boundaries
  those plans establish: `@internal` methods, split view tags, extendable
  models, lifecycle events). If run in the same batch, do this LAST.
- **Risk**: LOW
- **Category**: docs
- **Planned at**: commit `061a4c0`, 2026-07-20

## Why this matters

A v1 tag is a semver promise, but right now the boundary between "covered by
semver" and "internal, may change" is implicit — spread across `final` keywords,
publish tags, and route code. The single highest-leverage pre-v1 artifact is a
short document that states the boundary plainly, so that: (a) users know what
they can rely on, (b) the maintainer has a reference when judging whether a
future change is breaking, and (c) predictable questions ("can I extend the page
model?", "is `window.Docent` stable?", "why does my slug beginning with `_`
404?") have a written answer. This plan writes that document and annotates the
handful of internal contracts the audit flagged so the code agrees with the doc.

## Current state

- No `COMPATIBILITY.md` exists at the package root (confirm: `ls COMPATIBILITY.md`
  → not found).
- Eight interfaces exist; only `DocumentationComponent`
  (`src/Runtime/Contracts/DocumentationComponent.php`) is a documented host
  extension point. The rest are container-bound and swappable but not intended
  as host SPI:
  - `src/Content/Repositories/DocumentationRepository.php`
  - `src/Content/Repositories/StoredPageRepository.php`
  - `src/Content/Repositories/LockAwareRepository.php`
  - `src/Content/Repositories/RedirectCollisionRepository.php`
  - `src/Documents/Renderer/CodeBlockRenderer.php`
  - `src/Documents/Parser/DocumentParser.php`
  - `src/Validation/Check.php`
- `resources/guides/authoring.md` documents the authoring dialect but does not
  state that page slugs must not begin with `_` (the reserved-route prefix).
  Confirm with `grep -n "_" resources/guides/authoring.md` — no reserved-prefix
  rule is present.

**Convention to match**: root-level docs are GitHub-flavored Markdown (see
`README.md`). Interface `@internal` annotations go in the interface's PHPDoc
block, same as plan 008's method annotations.

## Commands you will need

| Purpose   | Command             | Expected            |
|-----------|---------------------|---------------------|
| Analyse   | `composer analyse`  | exit 0, no errors   |
| Lint      | `composer lint`     | exit 0              |
| Tests     | `composer test`     | all pass, exit 0    |

## Scope

**In scope**:
- `COMPATIBILITY.md` (create at package root — full content provided below)
- `src/Content/Repositories/StoredPageRepository.php`,
  `LockAwareRepository.php`, `RedirectCollisionRepository.php`,
  `src/Validation/Check.php` (add `@internal` to the interface docblock)
- `resources/guides/authoring.md` (add the reserved-slug rule)

**Out of scope** (do NOT touch):
- `src/Runtime/Contracts/DocumentationComponent.php` — the ONE public extension
  interface; leave it un-annotated.
- `src/Content/Repositories/DocumentationRepository.php`,
  `src/Documents/Renderer/CodeBlockRenderer.php`,
  `src/Documents/Parser/DocumentParser.php` — these are the plausibly-swappable
  ones; the maintainer has NOT decided to close them, so leave them un-annotated
  (COMPATIBILITY.md describes them as "advanced, may evolve" rather than
  `@internal`).
- Any JS file — the JS surface stance is documented in prose only; do not edit
  `resources/js/**` or `resources/dist/**` (that would trigger the compiled-asset
  drift check).
- No route or provider code changes — the reserved-slug contract is documented,
  not re-implemented.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Create `COMPATIBILITY.md`

Create the file at the package root with EXACTLY this content:

```markdown
# Compatibility & Versioning

Docent follows [semantic versioning](https://semver.org). This document defines
what the public API is — what a minor or patch release will not break — and what
is internal and may change at any time.

## Covered by semver (safe to rely on)

- **The `Docent` facade registration DSL**: `value()`, `link()`, `condition()`,
  `audience()`, `component()`, `suggest()`, and `site()`.
- **The `Docent` facade read methods** documented on the facade:
  `page()`, `url()`, `siteName()`, `navigation()`, `contextFor()`, `registry()`.
- **Configuration keys** in `config/docent.php`. New keys may be added; existing
  keys keep their meaning within a major version.
- **Artisan commands** and their options: `docent:install`, `docent:clear`,
  `docent:check`, `docent:guide`, `docent:insights:prune`.
- **URL shapes** under a site's route prefix: `/{slug}`, `/{slug}.md`,
  `/llms.txt`, `/llms-full.txt`, `/sitemap.xml`. The `_`-prefixed paths
  (`/_search`, `/_ask`, `/_widget`, `/_assets`, `/_uploads`, `/_insights`) are
  reserved internal routes — stable as endpoints, but not user-authored.
- **The authoring dialect**: front-matter keys, dynamic tokens
  (`{{ value:… }}`, `{{ link:… }}`, `{{ route:… }}`), directives, and content
  components, as documented in the authoring guide and `docent:guide`.
- **The database schema** shipped in migrations, and the models
  `DocentPage`, `DocentPageRevision`, `AiQuestion`, `InsightEvent`. These models
  are not `final`; you may extend them for your own use.
- **Page lifecycle events**: `PageSaved`, `PagePublished`, `PageUnpublished`,
  `PageDeleted` (namespace `STS\Docent\Content\Events`).
- **The `DocumentationComponent` contract** for custom content components.
- **Publishable templates under the `docent-views` tag** (the layout, page,
  landing, and widget templates) and the view-data payload the layout receives.
- **Publishable language keys** (`docent-lang`) and the `<x-docent::widget>`
  Blade component.
- **The `window.Docent(...)` JavaScript command queue** and the
  `<x-docent::widget>` embed.

## Internal (may change in any release)

- **`DocentManager` methods marked `@internal`** — everything beyond the
  facade-documented surface above. `DocentManager` is reachable via
  `Docent::site()`, but only the documented methods are promised.
- **Blade templates published under `docent-views-internal`**, and every partial
  under `partials/**`, `widget/**`, and the `hero`/`search-box`/`section-cards`
  components. Override them at your own risk; their names and structure may
  change.
- **`data-docent-*` HTML attributes, the widget config JSON payload, and
  `window.docentUiStrings`** — build-time details of the reader/widget UI, not a
  scripting API.
- **Repository and renderer sub-interfaces** (`StoredPageRepository`,
  `LockAwareRepository`, `RedirectCollisionRepository`, `Validation\Check`) are
  internal collaborators. `DocumentationRepository`, `CodeBlockRenderer`, and
  `DocumentParser` are swappable via the container for advanced use, but their
  method sets may evolve — pin your Docent version if you replace them.
- **Any class or method annotated `@internal`.**

## Intentional naming choices

These are deliberate and stable, not oversights:

- The content element is `<docs-component name="…" />` (reads naturally for
  authors), while the registration method is `Docent::component()`.
- `docent:insights:prune` is namespaced under `insights` to leave room for
  future `docent:insights:*` commands.

## Overriding views safely

`vendor:publish --tag=docent-views` publishes only the templates intended for
branding overrides. The stable contract is the *view-data payload* those
templates receive, not the internal partials they include. If you need to fork a
partial, publish `docent-views-internal` and pin your Docent version.
```

**Verify**: `test -f COMPATIBILITY.md && head -1 COMPATIBILITY.md` → prints
`# Compatibility & Versioning`.

### Step 2: Annotate the internal contracts

Add `@internal` to the interface-level PHPDoc of these four files (create a
`/** @internal */` block above the `interface` keyword if none exists, or add an
`@internal` line to the existing docblock):
- `src/Content/Repositories/StoredPageRepository.php`
- `src/Content/Repositories/LockAwareRepository.php`
- `src/Content/Repositories/RedirectCollisionRepository.php`
- `src/Validation/Check.php`

Do NOT annotate `DocumentationRepository`, `CodeBlockRenderer`,
`DocumentParser`, or `DocumentationComponent`.

**Verify**:
- `grep -l "@internal" src/Content/Repositories/StoredPageRepository.php src/Content/Repositories/LockAwareRepository.php src/Content/Repositories/RedirectCollisionRepository.php src/Validation/Check.php`
  → four files.
- `grep -L "@internal" src/Runtime/Contracts/DocumentationComponent.php src/Content/Repositories/DocumentationRepository.php src/Documents/Renderer/CodeBlockRenderer.php src/Documents/Parser/DocumentParser.php`
  → four files (confirming they were NOT annotated).
- `composer analyse` → exit 0.

### Step 3: Document the reserved-slug rule in the authoring guide

In `resources/guides/authoring.md`, in the "Files and slugs" section (near the
top, where slug rules are described), add a bullet stating that page slugs must
not begin with an underscore, because `_`-prefixed paths are reserved for
Docent's internal routes. Keep the wording consistent with the surrounding
bullets.

Suggested bullet:
```markdown
- A slug segment must not begin with `_`. Paths like `/_search` and `/_assets`
  are reserved for Docent's internal routes; a page slug starting with `_` is
  unreachable.
```

**Verify**: `grep -n "must not begin with \`_\`" resources/guides/authoring.md`
→ one match.

### Step 4: Confirm the guide command still emits the reference cleanly

`resources/guides/authoring.md` is printed by `docent:guide`. Confirm the added
bullet didn't break that command's test.

**Verify**: `vendor/bin/pest tests/Feature/GuideCommandTest.php` → all pass.

## Test plan

No new automated tests — this plan is documentation plus `@internal`
annotations. Guards:
- `composer analyse` — every edited docblock is well-formed.
- `composer test` — the existing `GuideCommandTest` still passes with the added
  authoring-guide bullet.
- `composer lint` — no style regressions.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `COMPATIBILITY.md` exists at the package root and begins with
      `# Compatibility & Versioning`.
- [ ] The four internal interfaces carry `@internal`; the four public/advanced
      interfaces do not (see Step 2 greps).
- [ ] `resources/guides/authoring.md` contains the reserved-slug bullet.
- [ ] `composer test` exits 0 (including `GuideCommandTest`).
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0.
- [ ] No files outside the in-scope list are modified (`git status`); no JS or
      dist file changed.

## STOP conditions

Stop and report back if:

- "Current state" doesn't match live code (drift since `061a4c0`).
- The COMPATIBILITY.md content references something that plans 007–011 did NOT
  actually establish (e.g. the doc says models are non-`final` but plan 010
  hasn't run yet, so they still are). If this plan runs before 007–011 in the
  batch, note the mismatch and report rather than editing the doc to match the
  old state.
- `composer analyse` errors on an annotated interface.
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: `COMPATIBILITY.md` is the source of truth for "is this
  change breaking?". When any future PR changes a symbol, check whether the doc
  lists it as covered. Keep the doc and the `@internal` annotations in sync.
- This should eventually be mirrored (in friendlier prose) as a docs page on the
  public docs site (docent-docs repo) — deferred to the maintainer, out of scope
  here since that is a different repository.
- If the maintainer later decides to close `DocumentationRepository` /
  `CodeBlockRenderer` / `DocumentParser` as internal, move them to the internal
  list here and add `@internal` in a follow-up.
