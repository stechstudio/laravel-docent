# Plan 015: Opt-in authoring-quality lint rules on the docent:check substrate

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Validation src/Console/CheckCommand.php config/docent.php`
> The working tree already holds uncommitted batches (plans 007–014). In
> particular **plan 013 is present and is the foundation for this plan** — see
> "Working tree & prerequisites". Do NOT treat 013's changes as drift; build on
> them. Only unexpected changes to THIS plan's other Current-state excerpts are
> drift.

## Status

- **Priority**: P3
- **Effort**: M
- **Risk**: LOW
- **Depends on**: plan 013 (its `docent.check.rules` config + `applyOverrides`
  severity pass are the substrate this plan extends). 013 is already implemented
  in the working tree.
- **Category**: dx (agent-authoring loop)
- **Planned at**: commit `061a4c0`, 2026-07-21

## Why this matters

`docent:check` today answers "will this break?" — broken links, unknown
integrations, malformed front matter. It does not answer "is this good?" — the
authoring-quality layer every mature docs linter has (Vale, Nimbus's lint
engine). This plan adds that layer as **opt-in** rules: off by default (so no
existing clean site suddenly reports warnings), enabled per rule via the
`docent.check.rules` config that plan 013 introduced. Two exemplar rules ship
now — `single-h1` and `description-length` — but the real deliverable is the
**opt-in rule infrastructure**: once it exists, adding more quality rules is a
one-class change. This directly serves the agent-authoring loop (an agent can be
told to enable stricter rules and self-correct against them).

## Working tree & prerequisites

The working tree contains uncommitted, already-reviewed batches (plans 007–014).
**Plan 013 is the foundation for this plan and is already applied** — do not
revert it. Its relevant, already-present code:

- `config/docent.php` has a `check` section:
  ```php
  'check' => [
      'rules' => [
          // 'unknown-icon' => 'warning',
      ],
  ],
  ```
- `src/Console/CheckCommand.php` reads it and applies severity overrides:
  ```php
  $overrides = is_array($config['check']['rules'] ?? null) ? $config['check']['rules'] : [];
  $issues = $this->applyOverrides($issues, $overrides);
  ```
  and has `private function applyOverrides(array $issues, array $overrides): array`
  that drops `'off'` rules and remaps `'error'`/`'warning'`/`'warn'`.
- `src/Validation/Issue.php` has `withSeverity(Severity $severity): self`.

**Off-limits** — do NOT modify, revert, or restage any file from plans 007–012
(`src/Documents/Renderer/HtmlRenderer.php`, `src/DocentManager.php`,
`src/DocentServiceProvider.php`, the four models, `src/Content/Events/*`,
`COMPATIBILITY.md`, `resources/guides/authoring.md`, the view components,
`src/Content/Repositories/*`, `src/Validation/Check.php`, and their tests) or
plan 014 (`src/Console/InstallCommand.php`). Never touch `plans/`,
`.design-logo-board.html`, or `resources/dist/*`.

## Current state

The check engine is a set of `Check` implementations run over a shared context:

- `src/Validation/Check.php` — `interface Check { public function run(CheckContext $context): iterable; }` (marked `@internal`).
- `src/Validation/CheckContext.php` — provides `pages()` (returns
  `list<PageReference>`, each with `->slug`, `->title`, `->description` (nullable),
  `->hidden`, etc.) and `document(string $slug): ?Document` (parsed AST).
- `src/Validation/AstWalker.php` — `AstWalker::walk($document)` yields every AST
  `Node` depth-first.
- `src/Documents/Ast/Heading.php` — `final class Heading extends Node` with a
  public `int $level` and `?int $line` (via `Node`).
- `src/Validation/Issue.php` — `Issue::warning(string $check, string $slug, string $message, ?int $line = null)`.
- `src/Validation/DocsChecker.php` — `run()` iterates all checks:
  ```php
  public function run(CheckContext $context): array
  {
      $issues = [];
      foreach ($this->checks as $check) {
          foreach ($check->run($context) as $issue) {
              $issues[] = $issue;
          }
      }
      return $issues;
  }
  ```
  `withDefaults()` lists ~23 checks (the full `docent:check` suite);
  `references()` lists the subset the admin runs inline on drafts.

**Existing rule to mirror** — `src/Validation/Checks/HeadingHierarchyCheck.php`
shows the exact pattern for an AST-walking, warning-emitting check:
```php
final class HeadingHierarchyCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);
            if ($document === null) { continue; }
            foreach (AstWalker::walk($document) as $node) {
                if (! $node instanceof Heading) { continue; }
                // ... yield Issue::warning('heading-hierarchy', $page->slug, '...', $node->line);
            }
        }
    }
}
```

**Callers of `run()`** (both must keep working):
- `src/Console/CheckCommand.php:69` — `DocsChecker::withDefaults()->run($context)`.
- `src/Admin/Editor.php:410` — `DocsChecker::references()->run($context)` (admin
  inline draft check — must NOT gain opt-in rules).

## Commands you will need

| Purpose   | Command                                        | Expected          |
|-----------|------------------------------------------------|-------------------|
| Tests     | `composer test`                                | all pass, exit 0  |
| One file  | `vendor/bin/pest tests/Feature/CheckCommandTest.php` | all pass    |
| Lint      | `composer lint`                                | exit 0            |
| Analyse   | `composer analyse`                             | exit 0, no errors |

## Scope

**In scope**:
- `src/Validation/OptInCheck.php` (create — the marker interface)
- `src/Validation/Checks/SingleH1Check.php` (create)
- `src/Validation/Checks/DescriptionLengthCheck.php` (create)
- `src/Validation/DocsChecker.php` (gate opt-in checks in `run()`; register the
  two new checks in `withDefaults()` only)
- `src/Console/CheckCommand.php` (pass the enabled-rule set into `run()`)
- `config/docent.php` (extend the `check` section's comment to mention opt-in
  quality rules — no behavior change)
- `tests/fixtures/quality-docs/index.md` (create — a fixture that trips both rules)
- `tests/Feature/CheckCommandTest.php` (add opt-in on/off cases)

**Out of scope** (do NOT touch):
- `DocsChecker::references()` and `src/Admin/Editor.php` — opt-in quality rules
  are for `docent:check`, never the admin inline draft check.
- Any existing `Check` class or its rule id.
- Plan 007–014 files (see "Working tree & prerequisites").

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Add the `OptInCheck` marker interface

Create `src/Validation/OptInCheck.php`:
```php
<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

/**
 * A quality/style check that is OFF by default and runs only when its rule id is
 * enabled in `docent.check.rules` (to `error`, `warning`, or `warn`). Correctness
 * checks implement {@see Check} directly and always run; opt-in checks add this
 * marker so `docent:check` can skip them unless the site opts in.
 *
 * @internal
 */
interface OptInCheck extends Check
{
    /** The stable rule id this check emits (matches the Issue `check` slug). */
    public function rule(): string;
}
```

**Verify**: `composer analyse` → exit 0.

### Step 2: Gate opt-in checks in `DocsChecker::run()`

Change `run()` to accept an enabled-rule set and skip `OptInCheck`s not in it.
Keep the parameter optional so `Editor.php`'s call keeps working unchanged:
```php
/**
 * @param  list<string>  $enabledRules  Rule ids of opt-in checks to run.
 * @return list<Issue>
 */
public function run(CheckContext $context, array $enabledRules = []): array
{
    $issues = [];

    foreach ($this->checks as $check) {
        if ($check instanceof OptInCheck && ! in_array($check->rule(), $enabledRules, true)) {
            continue;
        }

        foreach ($check->run($context) as $issue) {
            $issues[] = $issue;
        }
    }

    return $issues;
}
```
Add the two new checks to `withDefaults()` (append to the array). Do NOT add them
to `references()`.

**Verify**: `composer analyse` → exit 0; `composer test` → all pass (nothing
enables the new rules yet, so behavior is unchanged).

### Step 3: Implement `SingleH1Check`

`src/Validation/Checks/SingleH1Check.php` — a page's title front matter renders as
its H1, so a body-level `#` heading is a duplicate H1. Warn on any `Heading` with
`level === 1`. Mirror `HeadingHierarchyCheck`'s structure exactly.
```php
final class SingleH1Check implements OptInCheck
{
    public function rule(): string
    {
        return 'single-h1';
    }

    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);
            if ($document === null) { continue; }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof Heading && $node->level === 1) {
                    yield Issue::warning(
                        'single-h1',
                        $page->slug,
                        'Body contains an h1; the page title already renders as the h1. Start body headings at h2.',
                        $node->line,
                    );
                }
            }
        }
    }
}
```

### Step 4: Implement `DescriptionLengthCheck`

`src/Validation/Checks/DescriptionLengthCheck.php` — an over-long `description`
front-matter value hurts SEO/search snippets/unfurls (the authoring guide advises
≤ ~160 chars). Warn when a description is present and exceeds 160 characters. Do
NOT warn on a missing description (many valid pages omit it — that would be noisy).
```php
final class DescriptionLengthCheck implements OptInCheck
{
    private const MAX = 160;

    public function rule(): string
    {
        return 'description-length';
    }

    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $description = $page->description;

            if (is_string($description) && mb_strlen($description) > self::MAX) {
                yield Issue::warning(
                    'description-length',
                    $page->slug,
                    'Description is '.mb_strlen($description).' characters; keep it under '.self::MAX.' for SEO and search snippets.',
                    null,
                );
            }
        }
    }
}
```
(Confirm the property is `PageReference->description` and nullable by reading
`src/Content/PageReference.php`; if the accessor differs, match it.)

**Verify**: `composer analyse` → exit 0.

### Step 5: Wire the enabled-rule set through `CheckCommand`

In `src/Console/CheckCommand.php`, where `$overrides` is already computed (from
plan 013), derive the enabled opt-in rules (any rule mapped to something other
than `'off'`) and pass them into `run()`:
```php
$overrides = is_array($config['check']['rules'] ?? null) ? $config['check']['rules'] : [];
$enabled = array_keys(array_filter(
    $overrides,
    static fn (mixed $severity): bool => is_string($severity) && $severity !== 'off',
));
```
Then change the run call (line ~69):
```php
$issues = [...$issues, ...DocsChecker::withDefaults()->run($context, $enabled)];
```
`$enabled` must be computed BEFORE the site loop that calls `run()` (it comes from
the top-level `$config`, so compute it once near where `$overrides` is read). Note
the existing code reads `$overrides` after the loop for `applyOverrides`; you will
now need the map available both before the loop (for `$enabled`) and after (for
`applyOverrides`). Read it once, early, and reuse — do not read the config twice.

**Verify**: `composer analyse` → exit 0.

### Step 6: Fixture + tests

Create `tests/fixtures/quality-docs/index.md` — a single page that trips both
rules when they're enabled:
```markdown
---
title: Quality Sample
description: This description is deliberately far too long to pass the description length rule, padded well beyond one hundred and sixty characters so the linter has something to complain about here.
---

## Intro

# A body level one heading

Body text.
```
(Confirm the description string is > 160 chars; pad it if not.)

Add to `tests/Feature/CheckCommandTest.php` using the existing `check()` helper:
```php
it('does not run opt-in quality rules by default', function () {
    [, $output] = check('quality-docs', ['--format' => 'json']);
    $checks = array_column(json_decode($output, true)['issues'], 'check');

    expect($checks)->not->toContain('single-h1')
        ->and($checks)->not->toContain('description-length');
});

it('runs an opt-in rule only when enabled in config', function () {
    config()->set('docent.check.rules', ['single-h1' => 'warning', 'description-length' => 'warning']);
    [, $output] = check('quality-docs', ['--format' => 'json']);
    $checks = array_column(json_decode($output, true)['issues'], 'check');

    expect($checks)->toContain('single-h1')
        ->and($checks)->toContain('description-length');
});

it('can promote an opt-in rule to an error', function () {
    config()->set('docent.check.rules', ['single-h1' => 'error']);
    [$exit, $output] = check('quality-docs', ['--format' => 'json']);
    $issues = json_decode($output, true)['issues'];

    $single = array_values(array_filter($issues, fn ($i) => $i['check'] === 'single-h1'));
    expect($single[0]['severity'])->toBe('error');
    expect($exit)->toBe(1);
});
```

**Verify**: `composer test` → all pass, including the three new cases.

## Test plan

- `tests/fixtures/quality-docs/index.md` (new): trips both rules.
- Three new cases in `tests/Feature/CheckCommandTest.php`: off-by-default,
  on-when-enabled, promotable-to-error (which also proves the 013 severity pass
  composes with opt-in gating).
- Structural patterns: `HeadingHierarchyCheck` for the checks, the existing
  `check()`-based tests for the feature tests.
- Verification: `composer test` → all pass.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0 with the three new cases passing.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `src/Validation/OptInCheck.php`, `Checks/SingleH1Check.php`, and
      `Checks/DescriptionLengthCheck.php` exist and implement `OptInCheck`.
- [ ] `grep -n "SingleH1Check\|DescriptionLengthCheck" src/Validation/DocsChecker.php`
      shows both registered in `withDefaults()` and NEITHER in `references()`.
- [ ] A default `docent:check` run on a page with a body h1 does NOT report
      `single-h1` (proven by the off-by-default test).
- [ ] No plan 007–014 file is modified (`git status`); `references()`/`Editor.php`
      unchanged.

## STOP conditions

Stop and report back if:

- Plan 013's `check` config / `applyOverrides` / `Issue::withSeverity` are NOT
  present in the working tree — this plan depends on them; report rather than
  reimplementing.
- `PageReference` exposes the description differently than `->description`
  (nullable string) — report the real shape.
- Adding the optional `$enabledRules` param to `run()` breaks the `Editor.php`
  caller's static analysis — report (it should be safe; the param has a default).
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: the two rule ids (`single-h1`, `description-length`) and the
  opt-in mechanism (a rule runs when set to any non-`off` severity in
  `docent.check.rules`) become public API. Confirm the "enabled = present and not
  off" semantics read well — it means a site turns a quality rule on by naming it
  with a severity, and off by omitting it or setting `'off'`.
- Adding future quality rules is now a one-class change: implement `OptInCheck`,
  register in `withDefaults()`, done. Candidates for later: `bare-url` (a link
  whose label equals its destination), `todo-marker` (TODO/FIXME left in body),
  `missing-description`. Each is its own small follow-up.
- The opt-in rules are documented for authors/agents via `docent:guide` and the
  docs site — a docs update is a separate, non-code follow-up.
