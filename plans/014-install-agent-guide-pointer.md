# Plan 014: `docent:install` writes a Docent authoring pointer into AGENTS.md / CLAUDE.md

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Console/InstallCommand.php`
> The working tree holds an uncommitted 007–012 batch (see "Working tree"); that
> is expected. Only a change to THIS plan's Current-state excerpt is drift.

## Status

- **Priority**: P3
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: dx (agent-authoring loop)
- **Planned at**: commit `061a4c0`, 2026-07-21

## Why this matters

Docent ships an agent-facing authoring guide (`php artisan docent:guide` prints
the dialect plus the app's live integration inventory; `docent:check` validates
the result). But nothing tells the host app's coding agent that any of this
exists — discovery is left to the human remembering. Coding agents read a
project-root `AGENTS.md` (or `CLAUDE.md`) for conventions. Having `docent:install`
drop a short, idempotent pointer there closes the loop: the next time an agent
opens the repo to write docs, it learns to run `docent:guide` first and
`docent:check` after. Small change, direct payoff for the "docs an agent can
operate" story.

## Current state

`src/Console/InstallCommand.php` — `final class InstallCommand` publishes config
and scaffolds starter docs. Relevant excerpt:
```php
protected $signature = 'docent:install {--with-database : Also publish the database store migrations}';

public function handle(SiteRegistry $sites): int
{
    $docent = $sites->site($sites->defaultKey());
    $this->call('vendor:publish', ['--tag' => 'docent-config']);

    $docs = $docent->config('filesystem.path') ?? resource_path('docs');

    $this->scaffold($docs.'/index.md', $this->indexStub());
    $this->scaffold($docs.'/getting-started/introduction.md', $this->introductionStub());

    // ... --with-database handling ...

    $this->newLine();
    $this->components->info('Docent installed.');
    $this->components->bulletList([ /* next-step bullets */ ]);

    return self::SUCCESS;
}

private function scaffold(string $path, string $contents): void
{
    if (File::exists($path)) {
        $this->components->twoColumnDetail($path, '<fg=yellow>exists</>');
        return;
    }
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);
    $this->components->twoColumnDetail($path, '<fg=green>created</>');
}
```
The command already uses `Illuminate\Support\Facades\File` and the
`twoColumnDetail` component style. It runs only when a human invokes it.

**Convention to match**: `File::exists`/`File::get`/`File::put`, the
`twoColumnDetail(path, status)` reporting style, and idempotence (the existing
`scaffold()` never overwrites). Use `base_path(...)` for project-root files.

## Working tree (read this)

The working tree holds an uncommitted, already-reviewed batch (plans 007–012).
Do NOT modify, revert, or restage any of those files, `plans/`,
`.design-logo-board.html`, or `resources/dist/*`. This plan touches only
`InstallCommand.php` and its test.

## Commands you will need

| Purpose  | Command                                    | Expected          |
|----------|--------------------------------------------|-------------------|
| Tests    | `composer test`                            | all pass, exit 0  |
| One file | `vendor/bin/pest tests/Feature/CommandsTest.php` | all pass    |
| Lint     | `composer lint`                            | exit 0            |
| Analyse  | `composer analyse`                         | exit 0, no errors |

## Scope

**In scope**:
- `src/Console/InstallCommand.php` (add the pointer-writing step + summary line)
- `tests/Feature/CommandsTest.php` (add coverage for the new behavior)

**Out of scope** (do NOT touch):
- Config, views, the guide command, or any 007–012 file.
- Do NOT change the existing config/scaffold behavior — this is additive.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Add an idempotent AGENTS.md/CLAUDE.md pointer step

Add a private method that writes a fenced, marker-delimited block into a
project-root agent context file, and call it from `handle()` before the final
"Docent installed." summary.

Behavior (idempotent and additive — never destroys existing content):
1. Choose the target: if `base_path('AGENTS.md')` exists, use it; else if
   `base_path('CLAUDE.md')` exists, use it; else create `base_path('AGENTS.md')`.
2. Build the block, delimited by stable markers so re-runs are idempotent:
   ```
   <!-- docent:guide start -->
   ## Documentation (Docent)

   Docs live in `{docsRelativePath}`. Before writing or editing docs, run
   `php artisan docent:guide` to get the authoring dialect and this app's
   registered values, links, conditions, audiences, and components. Validate
   changes with `php artisan docent:check` and fix everything it reports.
   <!-- docent:guide end -->
   ```
   Use the site's docs path relative to the project root when it's inside it
   (e.g. `resources/docs`), else the absolute path.
3. If the file exists and already contains `<!-- docent:guide start -->`, replace
   the existing block (between the markers, inclusive) with the freshly-built one
   — this keeps it current on re-run without duplicating. If the file exists
   without the markers, append the block (preceded by a blank line). If the file
   doesn't exist, create it with the block as its content.
4. Report via `twoColumnDetail($file, '<fg=green>updated</>' | '<fg=green>created</>')`.

Suggested implementation shape:
```php
use Illuminate\Support\Str;

private function writeAgentPointer(string $docsPath): void
{
    $target = File::exists(base_path('AGENTS.md'))
        ? base_path('AGENTS.md')
        : (File::exists(base_path('CLAUDE.md')) ? base_path('CLAUDE.md') : base_path('AGENTS.md'));

    $rel = Str::startsWith($docsPath, base_path())
        ? ltrim(Str::after($docsPath, base_path()), DIRECTORY_SEPARATOR)
        : $docsPath;

    $block = "<!-- docent:guide start -->\n"
        ."## Documentation (Docent)\n\n"
        ."Docs live in `".$rel."`. Before writing or editing docs, run\n"
        ."`php artisan docent:guide` to get the authoring dialect and this app's\n"
        ."registered values, links, conditions, audiences, and components. Validate\n"
        ."changes with `php artisan docent:check` and fix everything it reports.\n"
        ."<!-- docent:guide end -->";

    $existing = File::exists($target) ? File::get($target) : null;
    $status = 'created';

    if ($existing === null) {
        $contents = $block."\n";
    } elseif (str_contains($existing, '<!-- docent:guide start -->')) {
        $contents = (string) preg_replace(
            '/<!-- docent:guide start -->.*?<!-- docent:guide end -->/s',
            $block,
            $existing,
        );
        $status = 'updated';
    } else {
        $contents = rtrim($existing)."\n\n".$block."\n";
        $status = 'updated';
    }

    File::put($target, $contents);
    $this->components->twoColumnDetail($target, '<fg=green>'.$status.'</>');
}
```
Call it in `handle()` after the scaffold calls and before the summary:
```php
$this->writeAgentPointer((string) $docs);
```
Add `use Illuminate\Support\Str;` if not already imported.

Add a bullet to the existing `bulletList([...])` summary:
`'Told your coding agent about Docent in AGENTS.md — it will run docent:guide before writing docs'`.

**Verify**: `composer analyse` → exit 0.

### Step 2: Tests

Add to `tests/Feature/CommandsTest.php` (read the file first to match its setup —
it already exercises `docent:install`). Because the command writes to
`base_path(...)`, tests run against the Testbench skeleton's base path; assert on
that. Cover three cases:

1. **Creates AGENTS.md when none exists**: ensure neither `base_path('AGENTS.md')`
   nor `CLAUDE.md` exists (delete if present in the skeleton), run
   `docent:install`, assert `AGENTS.md` now exists and contains
   `<!-- docent:guide start -->` and `php artisan docent:guide`.
2. **Idempotent on re-run**: run install twice; assert the file contains exactly
   one `<!-- docent:guide start -->` marker
   (`substr_count($contents, '<!-- docent:guide start -->') === 1`).
3. **Appends to an existing AGENTS.md without destroying content**: pre-write
   `base_path('AGENTS.md')` with `"# My project\n"`, run install, assert the file
   still contains `# My project` AND the docent block.

Clean up any files the test writes into the skeleton in an `afterEach`/teardown
so other tests aren't affected (model cleanup after existing patterns in the
file; if the file already has a teardown for scaffolded docs, extend it).

**Verify**: `vendor/bin/pest tests/Feature/CommandsTest.php` → all pass.

## Test plan

- Three cases in `tests/Feature/CommandsTest.php`: create, idempotent re-run,
  append-preserving.
- Structural pattern: the existing `docent:install` test(s) in that file.
- Verification: `composer test` → all pass.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0 with the three new cases passing.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `grep -n "docent:guide start" src/Console/InstallCommand.php` → matches
      (the marker is emitted).
- [ ] Re-running `docent:install` never produces a second marker (covered by the
      idempotency test).
- [ ] No file from the 007–012 batch is modified (`git status`).

## STOP conditions

Stop and report back if:

- `InstallCommand.php` differs from the "Current state" excerpt beyond the
  expected 007–012 batch (real drift).
- Writing to `base_path(...)` in the test environment isn't possible or leaks
  into other tests you can't clean up — report so the reviewer can decide on a
  fake filesystem approach.
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: the block is additive and marker-delimited, so it never
  clobbers a user's AGENTS.md and stays idempotent across upgrades. Confirm the
  wording is the pointer we want agents to read (it names both `docent:guide` and
  `docent:check`).
- Consider (future, not now) a `--no-agent-guide` opt-out flag if any user finds
  the auto-write intrusive; deferred until someone asks.
- If the guide command is ever renamed, update the block text here too.
