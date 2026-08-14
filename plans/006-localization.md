# Plan 006: Localization — translatable UI strings + assistant answer-time translation

> **Executor instructions**: Follow this plan step by step. Run every verification
> command and confirm the expected result before moving on. If anything in "STOP
> conditions" occurs, stop and report — do not improvise. Update this plan's row in
> `plans/README.md` when done (leave `plans/` uncommitted).
>
> **Drift check (run first)**: `git diff --stat 713bcb8..HEAD -- src resources config lang`
> Plan 005 may land first and touch `layout.blade.php` / `DocentServiceProvider.php` —
> that's expected; re-locate excerpts by content, not line number.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW-MED (JS bundles must be rebuilt; wire behavior must not change for
  English installs)
- **Depends on**: none (safe after 005; execute 005 first to avoid layout merge noise)
- **Category**: feature
- **Planned at**: commit `713bcb8`, 2026-07-18

## Why this matters

Every user-facing string in Docent's reader UI is hardcoded English ("Search
documentation…", "Ask Assistant", "Copy", "Skip to content") — there is no `lang/`
directory at all, so a non-English Laravel app cannot ship Docent to its users.
Competitors ship publishable language files. Part B goes further than parity: the
assistant answers in the reader's language from English-authored docs (the Intercom
Fin pattern — author once, serve any language), which beats maintaining translated
content trees. Explicitly out of scope forever-until-demanded: per-locale content.

## Current state

- No `lang/` directory in the package; `grep -rn "__(" resources/views` → zero hits.
  All strings are literals in Blade and JS.
- `resources/views/layout.blade.php` — reader chrome strings: "Skip to content",
  "Open navigation" (aria), theme toggle labels, etc.
- `resources/views/partials/search.blade.php:28,33,74` — "Search documentation…",
  "Ask Assistant", "Ask Assistant about …".
- Assistant/widget/search partials under `resources/views/partials/` — sweep all
  (`grep -rn '[A-Za-z]' resources/views/partials --include='*.blade.php' | grep -v admin`)
  for user-visible literals. **Admin views (`resources/views/partials/admin/`,
  admin layout, docent-admin.js) are OUT of scope** — staff-facing, English for now.
- JS bundles with user-visible strings: `resources/js/docent.js`,
  `resources/js/docent-assistant.js` (e.g. "Copy", "Copied", aria-label "Copy code"),
  `resources/js/docent-widget.js`. Alpine components receive server data today via
  inline `@js(...)` arguments (`layout.blade.php:35` — `docentAssistant(...)`) and via
  `DocentManager::widgetConfig()` (`src/DocentManager.php`, `widgetConfig()` method)
  for the widget embed.
- AI ask path: `src/Ai/AiPrompt.php` builds the system prompt;
  `src/Ai/AiAnswerService.php` calls the provider; the answer cache key is built in
  `src/Http/Controllers/AskController.php` (`cacheKey($corpus, $conversation, $question, $mode)`).
  Existing AI tests use a fake provider — `grep -rln 'GroundedAnswersFake\|Prism' tests/`
  for the pattern.
- Service provider: `src/DocentServiceProvider.php` — add `loadTranslationsFrom` +
  a `docent-lang` publish tag next to the existing view/config publishing (~line 137).
- JS build: `npm run build` regenerates `resources/dist/*`; CI fails if dist drifts
  from source, so **rebuild and commit dist** with the source change.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `composer test` | all pass |
| Lint / analysis | `composer lint` && `composer analyse` | exit 0 |
| Rebuild JS/CSS | `npm run build` | exit 0; dist diff matches source change |
| Browser suite (final check) | `npx playwright test --reporter=line` | all pass (kill any stale server on port 8000 first: `lsof -ti :8000 \| xargs kill`) |

## Scope

**In scope**:

- `lang/en/ui.php` (create; package-root `lang/` dir)
- `src/DocentServiceProvider.php` (loadTranslationsFrom + publish tag)
- Reader/search/assistant/widget Blade views under `resources/views/` (NOT admin)
- `resources/js/docent.js`, `docent-assistant.js`, `docent-widget.js` + rebuilt `resources/dist/*`
- `src/DocentManager.php` (`widgetConfig()` gains a `strings` entry)
- `src/Ai/AiPrompt.php`, `src/Ai/AiAnswerService.php`, `src/Http/Controllers/AskController.php`, `config/docent.php` (Part B)
- `tests/Feature/LocalizationTest.php` (create), AI prompt test additions
- `README.md`, `CHANGELOG.md`

**Out of scope**:

- Admin panel strings and `docent-admin.js`.
- Per-locale content trees, locale-prefixed routes, `docent:check` locale rules.
- Changing any English default string's wording — extraction must be text-identical.
- Protected dirty files and `plans/` (see plans/README.md global rules).

## Git workflow

- Work on `main`. Two commits:
  1. `feat: make the reader UI translatable through publishable language files`
  2. `feat: let the assistant answer in the reader's language`
- **Never add Co-Authored-By or "Generated with" lines.** Do NOT push. Stage by explicit path.

## Steps — Part A: UI strings

### Step A1: Language file + wiring

Create `lang/en/ui.php` returning a flat-ish array grouped by surface, e.g.
`'search' => ['placeholder' => 'Search documentation…', ...]`, `'assistant' => [...]`,
`'widget' => [...]`, `'common' => ['skip_to_content' => ..., 'copy' => 'Copy', 'copied' => 'Copied', ...]`.
Values must be byte-identical to today's literals. In `DocentServiceProvider::boot()`
area where views/config are registered: `$this->loadTranslationsFrom(__DIR__.'/../lang', 'docent');`
plus `$this->publishes([...], 'docent-lang')`.

**Verify**: `php vendor/bin/testbench tinker --execute="echo __('docent::ui.common.copy');"`
→ `Copy` (or an equivalent one-liner test).

### Step A2: Blade extraction

Replace user-visible literals in the in-scope views with `{{ __('docent::ui.…') }}`
(including `aria-label`/`placeholder`/`title` attributes). Sweep systematically per
file; keep markup untouched otherwise. Parameterized strings use `:placeholders`
(e.g. "Ask Assistant about “:query”" — check how the query is interpolated at
`search.blade.php:74`; Alpine `x-text` interpolation must keep working, which may
mean splitting the string around the dynamic span rather than using a `:param`).

**Verify**: `composer test` → all pass; then
`grep -rn 'Search documentation…\|Skip to content' resources/views` → no matches
outside admin views.

### Step A3: JS strings

- Reader/assistant bundles: emit the strings once in `layout.blade.php` head:
  `<script>window.docentUiStrings = @js(__('docent::ui.common') + assistant/search groups as needed);</script>`
  (choose the minimal set the JS actually uses — inventory first with
  `grep -n '"[A-Z]\|'"'"'[A-Z]' resources/js/docent.js resources/js/docent-assistant.js`).
  In JS, read via a tiny helper with English fallbacks:
  `const str = (k, fallback) => window.docentUiStrings?.[k] ?? fallback;` so an
  un-updated published layout keeps working.
- Widget: add `'strings' => [...]` to `widgetConfig()` in `DocentManager` (translated
  server-side via `__()`), and read it in `docent-widget.js` the same fallback way.
- `npm run build`; commit dist with the source.

**Verify**: `composer test`; `git diff --stat resources/dist` shows the rebuilt
bundles; `npx playwright test tests/Browser/assistant.spec.js --reporter=line` → passes
(strings unchanged in English).

### Step A4: Localization test

`tests/Feature/LocalizationTest.php`: register an alternate locale translation at
runtime (Testbench: `app('translator')->addLines(['docent::ui.search.placeholder' => 'Rechercher…'], 'fr')`,
then `app()->setLocale('fr')`), request a docs page, assert the French string renders
and the English default renders under `en`. Commit Part A.

**Verify**: full gate green. `git show --stat HEAD` → only Part A files.

## Steps — Part B: assistant answer language

### Step B1: Config + resolution

In `config/docent.php` under `ai`, add a documented key:

```php
// null = answer in the docs' language (today's behavior).
// 'viewer' = answer in the app locale of the request (app()->getLocale()).
// Any locale string (e.g. 'de', 'pt-BR') = always answer in that language.
'language' => null,
```

Resolution happens once per ask (in `AskController` or `AiAnswerService`, whichever
already owns per-request assembly — read both first): resolve to `null` or a locale
string.

### Step B2: Prompt instruction

In `AiPrompt` (read it fully first), when a language is resolved, append one system
instruction, e.g.: `Respond in the language identified by BCP 47 code "{code}",
regardless of the language of the documentation excerpts.` Do not alter the
placeholder rules, citation rules, or any other instruction.

### Step B3: Cache correctness

The answer cache must not replay an answer across languages: include the resolved
language in the ask cache key built by `AskController::cacheKey(...)` (and any other
key that memoizes final answers — grep `answer_ttl` usages to find them all). A null
language must produce today's keys unchanged (append only when set) so existing
caches don't churn for English installs.

### Step B4: Tests + commit

- Unit/feature test: with `ai.language => 'viewer'` and locale `de`, the prompt
  captured by the fake provider contains the language instruction with `de`; with the
  default null config the instruction is absent (model after existing Ai prompt
  tests).
- Cache-key test: keys differ between two languages, identical to before when null.
- README + CHANGELOG notes. Commit Part B.

**Verify**: full gate green; `git show --stat HEAD` → only Part B files.

## Done criteria

- [ ] `composer test` / `lint` / `analyse` exit 0; new tests pass
- [ ] No user-visible English literal remains in in-scope reader views (grep spot-checks)
- [ ] English rendering byte-identical (browser suite passes unchanged)
- [ ] `resources/dist` rebuilt and committed with the JS source change
- [ ] With `ai.language` null, prompts and cache keys identical to before (test-proven)
- [ ] `plans/README.md` rows updated

## STOP conditions

- Any English string would have to change wording to be extractable.
- The Alpine interpolation in step A2 can't be preserved without restructuring markup.
- Part B requires touching the conversation-memory format or corpus builder.
- A browser test fails on string content in English.

## Maintenance notes

- New UI strings must go through `lang/en/ui.php` from now on — a reviewer should
  reject literal strings in reader views.
- The published-lang upgrade caveat mirrors published views: users who publish
  `docent-lang` own drift. Note it in the README paragraph.
- Per-locale content stays deliberately unbuilt; the documented workaround is a site
  per locale on multi-site.
