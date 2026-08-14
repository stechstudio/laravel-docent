# Plan 008: Mark internal API surface `@internal` and fix the stale `VERSION` constant

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. Do NOT update `plans/README.md` or commit — a
> reviewer maintains the index and handles git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/DocentManager.php src/DocentServiceProvider.php`
> If either changed, compare the "Current state" excerpts against the live code
> before proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none (annotations + one constant; behavior-preserving)
- **Category**: tech-debt (semver hygiene)
- **Planned at**: commit `061a4c0`, 2026-07-20

## Why this matters

Tagging v1.0 turns every reachable public symbol into a compatibility promise.
`DocentManager` is `final` but reachable by host code through the promised
`Docent::site()` facade method, and it exposes ~60 public methods — only about a
dozen are meant for host apps; the rest exist purely for the HTTP controllers
and Blade views. Without an `@internal` marker, a v1 tag freezes all 60
signatures, so refactoring rendering, auth, fingerprinting, or theming later
becomes a breaking change. `@internal` is a PHPDoc annotation with zero runtime
effect — it costs nothing now and cannot be added cleanly after people depend on
the methods.

Separately, `DocentManager::VERSION` is a public constant hardcoded to `'0.1.0'`.
It is already wrong for a v1 build and surfaces in `php artisan about`, so it is
both a frozen-forever API constant and a visible correctness bug.

Two undocumented Blade components (`hero`, `search-box`, `section-cards`) are
also reachable as `<x-docent::...>` and would become an implicit promise the
moment anyone uses them; they get an `@internal` note in the same pass.

## Current state

**Host-facing methods that STAY public and un-annotated** (the promised
surface — do NOT mark these `@internal`):
`condition()`, `value()`, `link()`, `component()`, `audience()`, `suggest()`
(the registration DSL, `src/DocentManager.php:72-125`), plus `page()`, `url()`,
`siteName()`, `navigation()`, `contextFor()`, `registry()`, and the `config()`
reader. These mirror the facade docblock at `src/Facades/Docent.php:11-24`.

**Internal collaborator methods to mark `@internal`** — all in
`src/DocentManager.php`, all currently plain `public function`:
`renderDocument()`, `redirectTarget()`, `partialDocument()`, `authorizes()`,
`audienceAllows()`, `sectionCards()`, `sectionCardsHtml()`, `widgetConfig()`,
`widgetSuggestions()`, `authorizedSuggestions()`, `viewerFingerprint()`,
`assistantStateNamespace()`, `layoutView()`, `resolveUrl()`,
`databaseHtmlPolicy()`, `guestContext()`, `themeStyles()`, `accent()`,
`logo()` and the other `logo*()`/`favicon()`/`fontHref()` theme readers,
`asset()`, `assetPath()`, `breadcrumb()`, `route()`, `routeName()`, `key()`,
`siteRef()`, `markdownUrl()`, `llmsUrl()`, `fullUrl()`, `widgetUrl()`,
`enableWidgetMode()`, `repository()`.

> The exact line numbers drift as the file changes; find each by name with
> `grep -n "public function <name>(" src/DocentManager.php`. Registration
> methods look like `src/DocentManager.php:72`:
> ```php
> public function condition(string $name, Closure|string $resolver, ?string $label = null, ?string $description = null): self
> ```

**The constant** — `src/DocentManager.php:50`:
```php
public const VERSION = '0.1.0';
```
Consumed once, in the about command, `src/DocentServiceProvider.php:300`:
```php
$details = ['Version' => DocentManager::VERSION];
```

**The Blade components** registered at `src/DocentServiceProvider.php:136`:
```php
Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'docent');
```
Files: `resources/views/components/{widget,hero,search-box,section-cards}.blade.php`.
Only `widget` is documented/public.

**Convention to match**: PHP 8 `@internal` goes in the method's PHPDoc block. The
codebase already uses class-level docblocks heavily (see the block above
`DocentManager` at `src/DocentManager.php:40-48`). For methods that currently
have NO docblock, add a one-line `/** @internal */`. For methods that already
have a docblock, add an `@internal` line to it. Do not change signatures or
bodies.

## Commands you will need

| Purpose   | Command             | Expected on success |
|-----------|---------------------|---------------------|
| Tests     | `composer test`     | all pass, exit 0    |
| Lint      | `composer lint`     | exit 0              |
| Analyse   | `composer analyse`  | exit 0, no errors   |
| About     | (see Step 3 verify) |                     |

## Scope

**In scope**:
- `src/DocentManager.php` (add `@internal` docblocks; change the `VERSION` const)
- `src/DocentServiceProvider.php` (version source for the about command, Step 3)
- `resources/views/components/{hero,search-box,section-cards}.blade.php`
  (add a Blade comment marking them internal)

**Out of scope** (do NOT touch):
- `src/Facades/Docent.php` — its `@method` docblock is the public contract;
  leave it. (Return-type fixes there are a SEPARATE plan, 010.)
- The registration methods and other host-facing methods listed above — they
  must remain un-annotated public API.
- Any method body or signature — this plan is annotations + one constant only.
- `resources/views/components/widget.blade.php` — it is public; do not annotate.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Annotate the internal `DocentManager` methods

For each method name in the "Internal collaborator methods" list above, locate
it with `grep -n "public function <name>(" src/DocentManager.php` and add
`@internal` to its docblock (create a `/** @internal */` one-liner if it has
none). Do NOT annotate the host-facing methods in the first list.

Example — a method with no existing docblock becomes:
```php
/** @internal */
public function viewerFingerprint(DocumentationContext $context): string
```
A method that already has a docblock gains an `@internal` line within it.

**Verify**:
- `grep -c "@internal" src/DocentManager.php` → at least 30.
- `composer analyse` → exit 0 (confirms no docblock is malformed).
- Spot check that registration methods stayed clean:
  `grep -B1 "public function value(" src/DocentManager.php` → the line above is
  the `@param` docblock, NOT `@internal`.

### Step 2: Retire the hardcoded `VERSION` constant value

Replace the frozen literal with a value derived at runtime from the installed
package metadata, so it is never stale and is not a promise to maintain by hand.
Use Composer's `InstalledVersions`:

In `src/DocentManager.php`, remove `public const VERSION = '0.1.0';` and add a
static accessor:
```php
/** @internal */
public static function version(): string
{
    return \Composer\InstalledVersions::getPrettyVersion('stechstudio/laravel-docent') ?? 'dev';
}
```
(`\Composer\InstalledVersions` ships with Composer 2 and is always autoloadable
in an installed package. The `?? 'dev'` covers a path/dev checkout where the
pretty version is null.)

**Verify**: `grep -n "const VERSION" src/DocentManager.php` → no matches.

### Step 3: Point the about command at the new accessor

In `src/DocentServiceProvider.php:300`, change:
```php
$details = ['Version' => DocentManager::VERSION];
```
to:
```php
$details = ['Version' => DocentManager::version()];
```

**Verify**:
- `grep -rn "DocentManager::VERSION" src/` → no matches (the old const is fully
  removed and unreferenced).
- `composer test` → all pass (confirms nothing else referenced the const).

### Step 4: Mark the internal Blade components

At the top of each of `resources/views/components/hero.blade.php`,
`search-box.blade.php`, and `section-cards.blade.php`, add a Blade comment:
```blade
{{-- @internal Docent component. Not part of the public API; props may change. --}}
```
Leave `widget.blade.php` untouched.

**Verify**: `grep -rl "@internal Docent component" resources/views/components/`
→ exactly three files (hero, search-box, section-cards).

## Test plan

No new tests — this plan is behavior-preserving (annotations + a version-source
swap). The guard is the existing suite plus static analysis:
- `composer test` proves the `VERSION` → `version()` swap broke no consumer.
- `composer analyse` proves every added/edited docblock is well-formed.

If `composer test` has a test asserting `DocentManager::VERSION === '0.1.0'`
(search: `grep -rn "VERSION" tests/`), that test encodes the old behavior —
STOP and report; do not edit it, since the reviewer needs to decide whether the
about-line assertion should move to `version()`.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `grep -c "@internal" src/DocentManager.php` → ≥ 30.
- [ ] `grep -rn "DocentManager::VERSION" src/` → no matches.
- [ ] `grep -B1 "public function value(" src/DocentManager.php` shows a `@param`
      line, not `@internal` (host-facing methods stayed public).
- [ ] Three component files carry the `@internal Docent component` comment;
      `widget.blade.php` does not.
- [ ] No files outside the in-scope list are modified (`git status`).

## STOP conditions

Stop and report back if:

- "Current state" excerpts don't match live code (drift since `061a4c0`).
- A test asserts on `DocentManager::VERSION` (see Test plan).
- `\Composer\InstalledVersions::getPrettyVersion` is unavailable in the test
  environment (the analyse/test step errors on it) — report so the reviewer can
  choose an alternate version source.
- You are unsure whether a given method is host-facing or internal because it is
  not in either list above — report it rather than guessing.

## Maintenance notes

- For the reviewer: the host-facing vs internal split is a product decision;
  scrutinize any method this plan marked `@internal` that you believe a host
  should be allowed to call. Adding it back to the public set later is
  non-breaking; the reverse is not — so err toward `@internal`.
- This plan pairs with plan 013 (documenting the v1 API stance), which will
  reference this `@internal` boundary in prose. Keep them consistent.
- `@internal` is advisory (IDEs/tools warn; PHP does not enforce). It does not
  prevent a determined host from calling the method — it documents that we do
  not promise it across minor versions.
