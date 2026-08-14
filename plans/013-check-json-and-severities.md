# Plan 013: Add `docent:check --format=json` and configurable per-rule severities

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Console/CheckCommand.php src/Validation config/docent.php`
> Note: the working tree already contains an **uncommitted, separately-reviewed
> batch (plans 007–012)** — see the "Working tree" note below. That is expected.
> Only treat a change to THIS plan's Current-state excerpts as drift.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none
- **Category**: dx (agent-authoring loop)
- **Planned at**: commit `061a4c0`, 2026-07-21

## Why this matters

Docent's pitch for coding agents is a tight loop: an agent writes a page, runs
`php artisan docent:check`, reads what's wrong, and fixes it before the PR — the
loop the new `docent:guide` command sets up. Today `docent:check` prints only
human-formatted console text, so an agent has to scrape prose to find the rule,
file, and line. Machine-readable output (`--format=json`) turns each finding
into a structured record the agent parses exactly. Separately, teams want to
tune which findings block: demote a rule to a warning or silence it entirely
without `--strict`'s all-or-nothing. Both are small because the domain model is
already diagnostic-shaped — this plan exposes what's already there.

## Current state

The check pipeline already models findings as structured, severity-tagged,
rule-identified records:

- `src/Validation/Issue.php` — `final class Issue` with readonly props
  `Severity $severity`, `string $check` (a stable kebab rule id, e.g.
  `broken-link`), `string $slug`, `string $message`, `?int $line`. Factories
  `Issue::error(...)` / `Issue::warning(...)`.
- `src/Validation/Severity.php` — `enum Severity: string { case Error = 'error';
  case Warning = 'warning'; }`.
- `src/Console/CheckCommand.php` — `handle()` collects `list<Issue> $issues`
  across sites, then:
  ```php
  $errors = $this->count($issues, Severity::Error);
  $warnings = $this->count($issues, Severity::Warning);

  if ($issues === []) {
      $this->components->info('Docent looks great — no problems found in '.$pages.' '.$this->pluralize('page', $pages).'.');
      return self::SUCCESS;
  }

  $this->render($issues);
  $this->summary($errors, $warnings);

  $strict = (bool) $this->option('strict');
  return $errors > 0 || ($strict && $warnings > 0) ? self::FAILURE : self::SUCCESS;
  ```
  Signature today:
  ```php
  protected $signature = 'docent:check
      {--strict : Treat warnings as failures}
      {--site= : Check only the selected Docent site}';
  ```
  `$this->render()` and `$this->summary()` write human console output with color
  tags. `handle()` already has `$config = (array) $this->laravel['config']->get('docent', [])`.

**The stable rule-id catalog** (the `check` slug on each Issue — these are public
identifiers a config will reference). Confirm with
`grep -rhoE "Issue::(error|warning)\(\s*'[a-z-]+'" src/Validation/Checks/*.php | grep -oE "'[a-z-]+'" | sort -u`:
`empty-code-group, empty-steps, empty-tabs, frame-without-image, front-matter,
include-cycle, invalid-code-group, invalid-navigation-link, missing-image,
missing-include, missing-title, orphan-step, orphan-tab, redirect-cycle,
redirect-external, redirect-missing, redirect-reserved, redirect-self,
search-keywords, unknown-ability, unknown-audience, unknown-component,
unknown-condition, unknown-icon, unknown-link, unknown-navigation-page,
unknown-navigation-route, unknown-route, unknown-suggestion, unknown-value,
video-missing-source, video-unrecognized-source` (plus `duplicate-slug`,
`redirect`, and any others emitted with a computed slug — do not hardcode this
list in code; the override map is open, keyed by whatever `check` slug an Issue
carries).

**Config shape to match** — shared sections in `config/docent.php` look like
(`config/docent.php:79`):
```php
'seo' => [
    'sitemap' => true,
    'image' => null,
],
```

**Test helper** — `tests/Feature/CheckCommandTest.php:15` defines
`check(string $fixture, array $parameters = []): array` returning
`[$exitCode, $output]`, pointing the repo at `tests/fixtures/<fixture>` (the
`broken-docs` fixture triggers many rules; a clean fixture like `docs` triggers
none). Use it for new tests.

## Working tree (read this)

The working tree currently holds an uncommitted, already-reviewed feature batch
(plans 007–012): changes to `HtmlRenderer.php`, `DocentManager.php`,
`DocentServiceProvider.php`, the four models, `src/Content/Events/*`,
`COMPATIBILITY.md`, `resources/guides/authoring.md`, view components, repository
interfaces, `src/Validation/Check.php`, plus new tests. **Do NOT modify, revert,
or restage any of those files.** This plan's in-scope files do not overlap them.
Leave `plans/`, `.design-logo-board.html`, and `resources/dist/*` untouched too.

## Commands you will need

| Purpose   | Command                                        | Expected          |
|-----------|------------------------------------------------|-------------------|
| Tests     | `composer test`                                | all pass, exit 0  |
| One file  | `vendor/bin/pest tests/Feature/CheckCommandTest.php` | all pass    |
| Lint      | `composer lint`                                | exit 0            |
| Analyse   | `composer analyse`                             | exit 0, no errors |

## Scope

**In scope**:
- `src/Console/CheckCommand.php` (add `--format`, apply severity overrides, JSON output)
- `src/Validation/Issue.php` (add a `withSeverity()` helper if needed)
- `config/docent.php` (add the `check` section)
- `tests/Feature/CheckCommandTest.php` (add JSON + severity-override cases)

**Out of scope** (do NOT touch):
- Any `src/Validation/Checks/*.php` rule — this plan does not add or change rules
  (that is a separate, later plan). It only re-labels and re-formats existing
  findings.
- `--strict` behavior — keep it exactly as is; it composes with overrides
  (overrides apply first, then `--strict` treats surviving warnings as failures).
- The 007–012 files listed in "Working tree".

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Add a severity-override + suppression pass

Add a `check` config section to `config/docent.php` (place it near `seo`/`search`
as a shared section):
```php
'check' => [
    // Override a rule's severity by its stable id, or silence it with 'off'.
    // e.g. 'heading-hierarchy' => 'warning', 'search-keywords' => 'off'.
    'rules' => [
        // 'unknown-icon' => 'warning',
    ],
],
```

In `Issue` (`src/Validation/Issue.php`), add an immutable helper:
```php
public function withSeverity(Severity $severity): self
{
    return new self($severity, $this->check, $this->slug, $this->message, $this->line);
}
```

In `CheckCommand::handle()`, after the `$issues` list is fully collected and
BEFORE `$errors`/`$warnings` are counted, apply the overrides. Read the map from
the already-loaded `$config`:
```php
$overrides = is_array($config['check']['rules'] ?? null) ? $config['check']['rules'] : [];
$issues = $this->applyOverrides($issues, $overrides);
```
Add the private method:
```php
/**
 * @param  list<Issue>  $issues
 * @param  array<string, mixed>  $overrides
 * @return list<Issue>
 */
private function applyOverrides(array $issues, array $overrides): array
{
    $result = [];

    foreach ($issues as $issue) {
        $override = $overrides[$issue->check] ?? null;

        if ($override === 'off') {
            continue; // suppressed
        }

        $severity = match ($override) {
            'error' => Severity::Error,
            'warning', 'warn' => Severity::Warning,
            default => $issue->severity, // unknown/absent → keep built-in
        };

        $result[] = $issue->severity === $severity ? $issue : $issue->withSeverity($severity);
    }

    return $result;
}
```
This runs for both console and JSON output, so `docent:check` and
`docent:check --format=json` agree.

**Verify**: `composer analyse` → exit 0.

### Step 2: Add the `--format` option and JSON output

Extend the signature:
```php
protected $signature = 'docent:check
    {--strict : Treat warnings as failures}
    {--format=console : Output format: console or json}
    {--site= : Check only the selected Docent site}';
```

In `handle()`, branch on the format when producing output. Keep the exit-code
logic identical for both formats. For JSON, emit a single JSON document to
stdout and return the same code. Shape:
```php
if ((string) $this->option('format') === 'json') {
    $this->output->writeln((string) json_encode([
        'ok' => $errors === 0 && (! $strict || $warnings === 0),
        'pages' => $pages,
        'errors' => $errors,
        'warnings' => $warnings,
        'issues' => array_map(static fn (Issue $i): array => [
            'check' => $i->check,
            'severity' => $i->severity->value,
            'slug' => $i->slug,
            'line' => $i->line,
            'message' => $i->message,
        ], $issues),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $errors > 0 || ($strict && $warnings > 0) ? self::FAILURE : self::SUCCESS;
}
```
Place this branch after `$issues`/`$errors`/`$warnings`/`$strict` are computed
and after Step 1's override pass, but BEFORE the human `render()`/`summary()`
calls, and make the empty-issues early-return also respect the format (in JSON
mode, an empty tree must still print a valid `{"ok":true,...,"issues":[]}`
document, not the human "looks great" line). Restructure so the JSON branch is
reached in both the empty and non-empty cases — e.g. compute `$issues`/counts
first, then: if JSON → emit JSON and return; else → the existing console path
(the `$issues === []` info line, else `render()` + `summary()`).

**Verify**: manual smoke — `vendor/bin/pest tests/Feature/CheckCommandTest.php`
still passes (existing console tests unchanged), then Step 3 adds JSON coverage.

### Step 3: Tests

Add to `tests/Feature/CheckCommandTest.php` using the existing `check()` helper:

```php
it('emits structured json with --format=json', function () {
    [$exit, $output] = check('broken-docs', ['--format' => 'json']);

    $data = json_decode($output, true);

    expect($data)->toBeArray()
        ->and($data['ok'])->toBeFalse()
        ->and($data['errors'])->toBeGreaterThan(0)
        ->and($data['issues'])->toBeArray()
        ->and($data['issues'][0])->toHaveKeys(['check', 'severity', 'slug', 'message']);
    expect($exit)->toBe(1);
});

it('emits valid json for a clean tree', function () {
    // Use a fixture that produces no issues; if unsure which, reuse the tree the
    // existing "no problems" test uses (grep this file for the clean fixture).
    [$exit, $output] = check('docs', ['--format' => 'json']);
    $data = json_decode($output, true);

    expect($data['ok'])->toBeTrue()
        ->and($data['issues'])->toBe([]);
    expect($exit)->toBe(0);
});

it('silences a rule via config override', function () {
    config()->set('docent.check.rules', ['broken-link' => 'off']);
    [, $output] = check('broken-docs', ['--format' => 'json']);
    $data = json_decode($output, true);

    $checks = array_column($data['issues'], 'check');
    expect($checks)->not->toContain('broken-link');
});

it('demotes an error rule to a warning via config override', function () {
    // Pick a rule that is an ERROR by default on the broken fixture (e.g.
    // 'missing-title' or 'broken-link'); confirm the run's exit flips to 0 when
    // that rule is the only error and it is demoted to a warning (non-strict).
    // If the fixture has multiple independent errors, assert the demoted rule's
    // severity in the JSON is 'warning' instead of asserting exit code.
    config()->set('docent.check.rules', ['broken-link' => 'warning']);
    [, $output] = check('broken-docs', ['--format' => 'json']);
    $data = json_decode($output, true);

    foreach ($data['issues'] as $issue) {
        if ($issue['check'] === 'broken-link') {
            expect($issue['severity'])->toBe('warning');
        }
    }
});
```
If the `docs` fixture is not the clean one used by the existing "no problems"
test, find the right fixture name by reading that test in this file. Do not
invent a fixture.

**Verify**: `composer test` → all pass, including the new cases.

## Test plan

- Four new cases in `tests/Feature/CheckCommandTest.php`: JSON on a broken tree,
  JSON on a clean tree, `off` suppression, and severity demotion.
- Structural pattern: the existing tests in the same file (they already use
  `check()` and assert on exit code + output).
- Verification: `composer test` → all pass.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0 with the new cases passing.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `vendor/bin/testbench docent:check --format=json` emits a single valid
      JSON object with `ok`, `pages`, `errors`, `warnings`, `issues[]` keys
      (verify: `vendor/bin/testbench docent:check --format=json | php -r 'json_decode(stream_get_contents(STDIN)); echo json_last_error() === JSON_ERROR_NONE ? "VALID\n" : "INVALID\n";'`).
      (Reviewer note: originally written as `php artisan ...`, which doesn't exist
      in a package repo — the executor correctly STOPPED on it; the reviewer ran
      the testbench equivalent, which passed. Criterion corrected 2026-07-21.)
- [ ] `grep -n "check.rules\|'check' =>" config/docent.php` shows the new section.
- [ ] Human `docent:check` output is unchanged when no `--format` is passed
      (existing console tests still pass).
- [ ] No file from the 007–012 batch (see "Working tree") is modified
      (`git status`).

## STOP conditions

Stop and report back if:

- "Current state" excerpts don't match the live code beyond the expected 007–012
  batch (real drift).
- Making the JSON branch also cover the empty-tree case forces restructuring
  that would change the human-console output — the console path must stay
  byte-identical. If you can't add JSON without altering console output, stop.
- The clean fixture for the "valid json for a clean tree" test can't be
  identified from the existing test file — report rather than guess.
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: the JSON shape and the `docent.check.rules` config key become
  public API (they're the agent-facing contract). Confirm the key names read
  well before this ships. `severity` values are the `Severity` enum's backing
  strings (`error`/`warning`) — stable.
- This is the substrate for a later "authoring-quality lint rules" plan (adds new
  rules like single-h1/bare-url): those just register more checks and default
  severities; the override map and JSON output already handle them.
- `'warn'` is accepted as an alias for `'warning'` in the override map for
  ergonomics; the canonical value emitted in JSON is always `warning`.
