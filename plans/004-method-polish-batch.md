# Plan 004: Targeted method polish — SearchEngine scoring, AskController pipeline, NavigationBuilder links, provenance constants

> **Executor instructions**: Follow this plan step by step. The four items are
> independent — commit each separately and run the verification between them. If
> anything in the "STOP conditions" section occurs, stop and report — do not
> improvise. When done, update the status row for this plan in `plans/README.md`
> (do not commit the `plans/` directory).
>
> **Drift check (run first)**: `git diff --stat a95a36a..HEAD -- src/Search/SearchEngine.php src/Http/Controllers/AskController.php src/Navigation/NavigationBuilder.php`
> Items 1–3 cite line numbers at commit `a95a36a`; if these files changed, re-locate by
> method name and compare excerpts before proceeding. Item 4 requires plan 001 DONE.

## Status

- **Priority**: P3
- **Effort**: S (×4 small items)
- **Risk**: LOW (item 1 is MED — ranking math; it has the strictest guard)
- **Depends on**: plans/001-extract-admin-editor.md (item 4 only — skip item 4 and mark it BLOCKED if 001 isn't done)
- **Category**: tech-debt
- **Planned at**: commit `a95a36a`, 2026-07-18

## Why this matters

Four small readability debts, each vetted and judged worth a mechanical fix — and
nothing more. Every item is behavior-preserving: **no test's expected values may
change**. These are the only method-level findings that survived a full-codebase audit;
everything else (renderer match tables, `CheckCommand::handle`, `SearchIndexer::sections`,
boolean flag params) was explicitly judged fine as-is — do not "improve" anything
beyond the four items below.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Full suite | `composer test` | all pass |
| Search tests | `vendor/bin/pest tests/ --filter=Search` | all pass |
| Ask tests | `vendor/bin/pest tests/ --filter=Ask` | all pass |
| Lint | `composer lint` | exit 0 |
| Static analysis | `composer analyse` | exit 0 |

## Git workflow

- Work on `main`. One commit per item, messages:
  1. `refactor: extract per-section scoring in SearchEngine`
  2. `refactor: flatten the ask pipeline's conversation and regenerate phases`
  3. `refactor: extract link target resolution in NavigationBuilder`
  4. `refactor: name the admin provenance strings`
- **Never add Co-Authored-By or "Generated with" lines.** Do NOT push.

---

## Item 1: `SearchEngine::score()` — extract the per-section scoring

**Current state** (`src/Search/SearchEngine.php:84-148`): one 64-line method with three
phases: (a) build `$globalFields`/`$globalText` maps and precompute per-term global
scores (lines 86–112), (b) loop sections, folding global + heading/body field scores
per term (116–131), (c) apply coverage weighting + phrase bonus and keep the best
section (133–145).

**Change**: extract phase (b)+(c)'s loop body into a private method

```php
/** @return array{float, SearchSection}|null  the weighted score, or null when no term matched */
private function scoreSection(SearchSection $section, array $globalScores, array $globalText, SearchQuery $query, SearchIndex $index): ?array
```

so `score()` reads: build globals → precompute term scores → `foreach` sections calling
`scoreSection` → keep the max (preserving the existing tie-break: higher score wins;
equal score prefers the lower `$section->order`). Copy the arithmetic **exactly** —
including `$coverage <= 0` semantics, the `0.4 + (1.6 * ($coverage ** 2))` multiplier,
and `phraseBonus`. Keep the existing comment about global scores being per-record.

**Verify**: `vendor/bin/pest tests/ --filter=Search` → all pass with unchanged
expectations. `composer test` → all pass.

**STOP if**: any search test asserts a different ranking/order/score after the change —
revert the item and report; do not tweak numbers to make tests pass.

---

## Item 2: `AskController::__invoke()` — extract the two obscuring phases

**Current state** (`src/Http/Controllers/AskController.php:43-123`): an 80-line entry
point. Two chunks obscure the happy path:

- lines 68–81: `$this->conversations->resolve(...)` inside a try/catch mapping
  `AiConversationForbidden` → 403 and `AiConversationExpired` → 409 JSON.
- lines 84–94: the regenerate guard (last turn must match the question, then
  `withoutLastTurn()`).

**Change**: extract each into a private method on the controller:

```php
private function resolveConversation(Request $request, DocumentationContext $context, string $corpusVersion, string $mode, array $validated): AiConversationResolution|JsonResponse
private function withoutRegeneratedTurn(AiConversation $conversation, string $question): AiConversation|JsonResponse
```

`__invoke` checks `instanceof JsonResponse` and early-returns. The try/catch stays
inside `resolveConversation` (it guards a domain-exception boundary — keep it exactly).
No behavior change: same status codes, same messages, same `code` keys.

**Verify**: `vendor/bin/pest tests/ --filter=Ask` → all pass. `composer analyse` → exit 0.

---

## Item 3: `NavigationBuilder::resolveLinks()` — extract per-target resolution

**Current state** (`src/Navigation/NavigationBuilder.php:148-212`): the loop body
validates entry shape (161–173), gates on ability (175–179), then an
`if page / elseif route / else url` chain (186–205) resolves `$url`/`$external`/`$active`
per target kind, with `continue` for invisible/missing targets.

**Change**: extract the chain into

```php
/** @return array{url: string, external: bool, active: bool}|null  null = skip this entry */
private function resolveLinkTarget(string $target, string $value, DocumentationContext $context, string $currentSlug, ?array &$pages): ?array
```

The lazily-built `$pages` map (line 156, `$pages ??= $this->pageMap()`) must keep its
build-once-per-call semantics — pass it by reference as shown, or restructure with a
small local closure; do NOT rebuild it per link. Loop body becomes: validate → gate →
`resolveLinkTarget(...) ?? continue` → append `NavigationLink`.

**Verify**: `composer test` → all pass (navigation tests unchanged). `composer lint` → exit 0.

---

## Item 4: Name the provenance strings in `Admin\Editor` (requires plan 001)

**Current state** (after plan 001, in `src/Admin/Editor.php` — formerly
`src/DocentManager.php:864,878,985-987,1240,1260`): the editor payloads use bare string
literals for provenance: `'store' => 'database'` / `'store' => 'filesystem'` (page
rows and details) and `'source' => 'database'` / `'file'` / `null` (group rows). These
are compared with `===` in `resources/js/docent-admin.js` and
`resources/views/partials/admin/group-settings.blade.php`, so a typo on the PHP side
silently breaks a badge — nothing fails.

**Change**: add class constants to `Editor` and use them at the producer sites:

```php
/** Where the effective page/group content comes from — the admin JS compares these exact strings. */
private const STORE_DATABASE = 'database';

private const STORE_FILESYSTEM = 'filesystem';

private const GROUP_SOURCE_DATABASE = 'database';

private const GROUP_SOURCE_FILE = 'file';
```

The **values must not change** — the JS/Blade consumers stay untouched. Do not rename
`'file'` to `'filesystem'` or unify with `DocumentSource::ORIGIN_*` (different wire
contract; a rename requires coordinated frontend changes that are out of scope).

**Verify**: `vendor/bin/pest tests/Admin` → all pass.
`grep -n "'store' => '" src/Admin/Editor.php` → no matches (all via constants).

---

## Test plan

No new tests: all four items are behavior-preserving refactors under existing coverage
(search ranking tests, ask feature tests, navigation tests, admin HTTP tests). The
suite passing with **unchanged expectations** is the proof.

## Done criteria

- [ ] Four commits (or three + a BLOCKED note for item 4 if plan 001 isn't done)
- [ ] `composer test`, `composer lint`, `composer analyse` all exit 0 after each commit
- [ ] No test expectation values changed anywhere (`git diff a95a36a..HEAD -- tests/` shows no assertion-value edits; new/renamed service resolution lines from plans 001–002 are fine)
- [ ] `git status` clean outside the four items' files
- [ ] `plans/README.md` rows updated (uncommitted)

## STOP conditions

- Any item forces a change to a test's expected values.
- Item 1: extraction changes any ranking result (see item guard).
- Item 3: you can't preserve the lazy `pageMap()` semantics cleanly.
- You feel the urge to refactor anything not listed here (renderers, `CheckCommand`,
  `SearchIndexer`, boolean flags, theming methods) — all explicitly out of scope.

## Maintenance notes

- Item 1: future ranking changes should land in `scoreSection` with a search test per
  change; the weighting constants are intentionally magic — don't extract them to config.
- Item 4: if the admin frontend vocabulary is ever unified (`'file'` vs `'filesystem'`),
  that's a coordinated JS+Blade+PHP change with its own plan.
