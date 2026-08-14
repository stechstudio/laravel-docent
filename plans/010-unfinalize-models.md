# Plan 010: Remove `final` from the four Eloquent models so host apps can extend them

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Content/Models src/Ai/Models src/Insights/Models`
> On any change, compare the "Current state" excerpts to the live code first; on
> a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: tech-debt (extensibility / semver)
- **Planned at**: commit `061a4c0`, 2026-07-20

## Why this matters

The four persisted models — `DocentPage`, `DocentPageRevision`, `AiQuestion`,
`InsightEvent` — are `final`, and there is no config key or container binding to
substitute a replacement class. Host apps routinely need to extend a package's
Eloquent models to add casts, relationships, scopes, or a custom connection.
With `final` and no rebind seam, the predictable "let me extend the page model"
request has no answer short of a v2.

The decision for v1 is to **drop `final`** on these four models. This is the
low-cost half of the extensibility story: subclassing works immediately for the
common cases (add a trait, a cast, a relationship on a child class the host
instantiates itself), and it is a non-breaking change to add a full
model-rebinding contract later if demand appears. Widening access (removing
`final`) is always safe; the reverse is not — so this must happen before the tag.

Note we are intentionally NOT adding a `docent.models` rebind config in this
plan (that is the heavier, deferrable half). Dropping `final` alone is the
decision the maintainer approved.

## Current state

All four are `final class ... extends Model`:

- `src/Content/Models/DocentPage.php:31` — `final class DocentPage extends Model`
  (uses `SoftDeletes`; has static `write()` and instance `publish()`,
  `unpublish()`, `revertTo()`).
- `src/Content/Models/DocentPageRevision.php:24` — `final class DocentPageRevision extends Model`
  (`const UPDATED_AT = null;`).
- `src/Ai/Models/AiQuestion.php:15` — `final class AiQuestion extends Model`.
- `src/Insights/Models/InsightEvent.php:32` — `final class InsightEvent extends Model`.

Excerpt, `src/Content/Models/DocentPage.php:31-35`:
```php
final class DocentPage extends Model
{
    use SoftDeletes;

    protected $guarded = [];
```

**Important — the `self`/`static` return-type interaction.** `DocentPage` has
methods that return `self` and instantiate via `self::` (e.g. `write()` at
`src/Content/Models/DocentPage.php:52` returns `self` and calls `self::on(...)`;
`revertTo()` at line 136 returns `self`). When a class is no longer `final`, a
`self` return type on a method that a subclass inherits still refers to
`DocentPage`, which is correct here (the factory always builds a `DocentPage`,
not the subclass). Do NOT change `self` to `static` — that would silently change
the contract. This plan removes the `final` keyword ONLY; it changes no method
signatures, bodies, or return types.

**Convention to match**: these models are otherwise idiomatic Laravel. Removing
`final` is a one-word deletion per file. Keep everything else — `SoftDeletes`,
`$guarded`, `$casts`, `const UPDATED_AT`, docblocks — exactly as is.

## Commands you will need

| Purpose   | Command             | Expected            |
|-----------|---------------------|---------------------|
| Tests     | `composer test`     | all pass, exit 0    |
| Lint      | `composer lint`     | exit 0              |
| Analyse   | `composer analyse`  | exit 0, no errors   |

## Scope

**In scope** (remove the `final` keyword only):
- `src/Content/Models/DocentPage.php`
- `src/Content/Models/DocentPageRevision.php`
- `src/Ai/Models/AiQuestion.php`
- `src/Insights/Models/InsightEvent.php`
- `tests/Unit/ModelExtensibilityTest.php` (create — proves subclassing works)

**Out of scope** (do NOT touch):
- Any method body, signature, return type (`self` stays `self`), property, or
  docblock in the four models.
- Any OTHER `final` class in `src/` — only these four models lose `final`. The
  package's discipline is that everything else stays `final`.
- Adding a `docent.models` config or container binding — explicitly deferred.
- Value objects and non-model classes — unchanged.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Remove `final` from the four model class declarations

In each of the four files, change `final class X extends Model` to
`class X extends Model`. Nothing else.

**Verify**:
- `grep -rn "^final class" src/Content/Models src/Ai/Models src/Insights/Models`
  → no matches.
- `grep -rn "^class .* extends Model" src/Content/Models src/Ai/Models src/Insights/Models`
  → four matches.
- `composer analyse` → exit 0 (PHPStan confirms the `self` return types are
  still valid on non-final classes).

### Step 2: Add a subclassing smoke test

Create `tests/Unit/ModelExtensibilityTest.php` proving a host can extend each
model. Model the file structure after an existing unit test that boots the
package (see `tests/Unit/InternalLinkTest.php` for the minimal Pest shape, or
any `tests/Feature` test that uses the DB for one that needs migrations).

The test defines a trivial subclass of each model and asserts it instantiates
and is a `Model`. For `DocentPage` (which needs the DB), assert a subclass can
be `new`-ed and reports the right table; a full persistence round-trip is not
required — the point is that `final` no longer blocks `extends`.

Minimal shape:
```php
it('allows host apps to extend the persisted models', function () {
    $page = new class extends \STS\Docent\Content\Models\DocentPage {};
    $revision = new class extends \STS\Docent\Content\Models\DocentPageRevision {};
    $question = new class extends \STS\Docent\Ai\Models\AiQuestion {};
    $event = new class extends \STS\Docent\Insights\Models\InsightEvent {};

    expect($page)->toBeInstanceOf(\STS\Docent\Content\Models\DocentPage::class)
        ->and($revision)->toBeInstanceOf(\STS\Docent\Content\Models\DocentPageRevision::class)
        ->and($question)->toBeInstanceOf(\STS\Docent\Ai\Models\AiQuestion::class)
        ->and($event)->toBeInstanceOf(\STS\Docent\Insights\Models\InsightEvent::class);
});
```
(Anonymous `class extends X {}` would fail to compile if `X` were still `final`,
so this test is a compile-time-plus-runtime guarantee of the change.)

**Verify**: `composer test` → all pass, including the new test.

## Test plan

- `tests/Unit/ModelExtensibilityTest.php` (new): asserts each of the four models
  can be subclassed. The anonymous subclasses would be a fatal error under
  `final`, so a green run is proof the keyword is gone and stays gone (a future
  re-`final` would break this test — the intended guard).
- Structural pattern: `tests/Unit/InternalLinkTest.php`.
- Verification: `composer test` → all pass.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0; `tests/Unit/ModelExtensibilityTest.php` exists and
      passes.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `grep -rn "final class" src/Content/Models src/Ai/Models src/Insights/Models`
      → no matches.
- [ ] `git diff src/**/Models` shows ONLY the `final ` keyword removed on the four
      class lines (no signature/body/return-type changes).
- [ ] No files outside the in-scope list are modified (`git status`).

## STOP conditions

Stop and report back if:

- "Current state" excerpts don't match live code (drift since `061a4c0`).
- Removing `final` causes `composer analyse` to report a `self` vs `static`
  covariance error on any model method — report it; do NOT "fix" it by changing
  `self` to `static`, since that alters the public contract.
- A verification fails twice after a reasonable fix attempt.
- You find a fifth persisted model not in the list — report it; do not assume it
  should also lose `final`.

## Maintenance notes

- For the reviewer: confirm no method return type was changed from `self` to
  `static`. The models' factory methods intentionally return the base class.
- Deferred: a `docent.models` rebind seam (config + container resolution so a
  host can make the package itself use their subclass). Adding it later is
  non-breaking. Only build it if users ask — dropping `final` covers the common
  "extend for my own use" case without it.
- Interaction with plan 011 (lifecycle events): if events are dispatched from
  model methods, a host subclass inherits that behavior automatically, which is
  the desired outcome.
