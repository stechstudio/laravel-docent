# Plan 016: `docent:make {type} {slug}` — scaffold a page from a content-type template

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Console src/DocentServiceProvider.php`
> The working tree holds uncommitted, already-reviewed batches (plans 007–015).
> `DocentServiceProvider.php` already carries plan 008's changes — that is
> expected, and you will make ONE additive edit to it (see Scope). Only
> unexpected changes to THIS plan's Current-state excerpts are drift.

## Status

- **Priority**: P3
- **Effort**: M
- **Risk**: LOW
- **Depends on**: none
- **Category**: dx (agent-authoring loop) / direction
- **Planned at**: commit `061a4c0`, 2026-07-21

## Why this matters

Good docs follow recognizable shapes — the Diátaxis quartet: a *tutorial*
teaches, a *how-to* accomplishes a task, a *reference* is for looking things up,
a *concept* explains. Authors (and coding agents) write better pages faster when
they start from the right skeleton instead of a blank file. `docent:make {type}
{slug}` scaffolds a starter page whose front matter and section outline encode
the chosen content type. It serves humans (a starting point) and agents (a target
structure they can fill in), and every scaffolded page is valid Docent dialect
that passes `docent:check` out of the box.

## Current state

Docent registers Artisan commands in the service provider and follows a
consistent command shape.

- `src/DocentServiceProvider.php:154` — the command registration array:
  ```php
  $this->commands([InstallCommand::class, ClearCommand::class, CheckCommand::class, GuideCommand::class, PruneInsightsCommand::class]);
  ```
  (This file also contains plan 008's unrelated changes elsewhere — do not touch
  those.)

- `src/Console/GuideCommand.php:22-31` — the site-resolution pattern to mirror:
  ```php
  public function handle(SiteRegistry $sites): int
  {
      $selected = $this->option('site');

      if ($selected !== null && ! $sites->has($selected)) {
          $this->components->error('Unknown Docent site ['.$selected.'].');

          return self::FAILURE;
      }
      // ...
  }
  ```

- `src/Sites/SiteRegistry.php` — `has(string $key): bool`, `defaultKey(): string`,
  `site(?string $key = null): DocentManager`. A site's docs path is
  `$sites->site($key)->config('filesystem.path')`, falling back to
  `resource_path('docs')` (see `InstallCommand`).

- `src/Console/InstallCommand.php` — the scaffold/idempotence pattern to mirror:
  ```php
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
  It uses `Illuminate\Support\Facades\File`. Titles elsewhere derive from a slug
  via `Illuminate\Support\Str::headline(Str::afterLast($slug, '/'))` (see
  `DocentPage::titleFor`).

**Backed-enum-with-method idiom**: this codebase prefers a backed enum with a
method over a conditional chain (e.g. `Severity`, `CalloutType`,
`AppLinkKind`). The content-type templates should be exactly that.

**`docent:check` compatibility** — scaffolded pages must pass the default check
suite. That means: front matter with a non-empty `title`; body starts at `##`
(no h1); no skipped heading levels; and any `::::steps` block must contain
non-empty `:::step` items (there is an `empty-steps` check). The stubs below
already satisfy all of this.

## Commands you will need

| Purpose   | Command                                    | Expected          |
|-----------|--------------------------------------------|-------------------|
| Tests     | `composer test`                            | all pass, exit 0  |
| One file  | `vendor/bin/pest tests/Feature/MakeCommandTest.php` | all pass |
| Lint      | `composer lint`                            | exit 0            |
| Analyse   | `composer analyse`                         | exit 0, no errors |
| Smoke     | `vendor/bin/testbench docent:make how-to sample/thing` | creates a file |

## Scope

**In scope**:
- `src/Content/ContentType.php` (create — the backed enum + stubs)
- `src/Console/MakeCommand.php` (create — the command)
- `src/DocentServiceProvider.php` (add `MakeCommand::class` to the `commands([...])`
  array — this ONE additive edit only; see below)
- `tests/Feature/MakeCommandTest.php` (create)

**The one off-limits-file exception**: you MAY add `MakeCommand::class` to the
`$this->commands([...])` array in `src/DocentServiceProvider.php` (line ~154),
and import it at the top. That is the ONLY change permitted in that file. Do NOT
alter anything else in it — especially plan 008's `version()` method or any
`@internal` annotations.

**Out of scope** (do NOT touch):
- Every other plan 007–015 file (renderers, models, events, check command,
  config, install command, etc.).
- Any config key — content types are a fixed built-in set, not configurable.
- `plans/`, `.design-logo-board.html`, `resources/dist/*`.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Create the `ContentType` enum with per-type stubs

Create `src/Content/ContentType.php`. A backed string enum with the Diátaxis four,
a `values()` helper, and a `scaffold(string $title): string` method returning the
page stub with the title interpolated. Use EXACTLY these four stubs (they are
valid Docent dialect and pass `docent:check`):

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Content;

/**
 * The Diátaxis content types Docent can scaffold. Each case carries a starter
 * page skeleton whose front matter and section outline encode that type's shape,
 * so `docent:make` gives authors (and coding agents) the right structure to fill
 * in rather than a blank file.
 */
enum ContentType: string
{
    case Tutorial = 'tutorial';
    case HowTo = 'how-to';
    case Reference = 'reference';
    case Concept = 'concept';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    public function scaffold(string $title): string
    {
        return match ($this) {
            self::Tutorial => <<<MD
                ---
                title: {$title}
                description: A learning-oriented walkthrough of what the reader will build.
                ---

                ## What you'll build

                One or two sentences on the end result and who this is for.

                ## Before you start

                - A prerequisite
                - Another prerequisite

                ## Steps

                ::::steps
                :::step Do the first thing
                Explain the first action and what the reader should see.
                :::
                :::step Do the next thing
                Continue the walkthrough to the finished result.
                :::
                ::::

                ## Recap

                What the reader accomplished, and where to go next.
                MD,
            self::HowTo => <<<MD
                ---
                title: {$title}
                description: A task-oriented guide to accomplishing a single goal.
                ---

                ## Goal

                State the one task this guide accomplishes.

                ## Before you start

                - What the reader needs in place first

                ## Steps

                ::::steps
                :::step First action
                The concrete step to take.
                :::
                :::step Second action
                The next concrete step.
                :::
                ::::

                ## Result

                How the reader confirms it worked.
                MD,
            self::Reference => <<<MD
                ---
                title: {$title}
                description: An information-oriented reference for looking things up.
                ---

                State what this reference documents. Reference pages are for
                lookup, not learning — keep prose minimal and structure scannable.

                ## Overview

                One or two sentences of orientation.

                ## Details

                | Name | Type | Description |
                | ---- | ---- | ----------- |
                | example | string | What it is. |

                ## Notes

                Edge cases, defaults, and gotchas worth recording.
                MD,
            self::Concept => <<<MD
                ---
                title: {$title}
                description: An understanding-oriented explanation of an idea.
                ---

                ## What it is

                Define the concept in one or two sentences.

                ## How it works

                Explain the mechanism — how the pieces fit together.

                ## Why it matters

                When this concept is relevant and what it lets the reader do.

                ## See also

                Point to related pages so the reader can go deeper.
                MD,
        };
    }
}
```
Note: PHP heredoc closing markers must sit at the correct indentation; if
`composer lint` (Pint) reformats them, let it. Verify the emitted content has NO
leading indentation on the markdown lines (heredoc preserves indentation up to
the closing marker's column — Pint's `<<<MD` indented-heredoc handling strips the
closing-marker indentation from each line, which is what you want).

**Verify**:
- `composer analyse` → exit 0.
- `php -r "require 'vendor/autoload.php'; echo STS\Docent\Content\ContentType::HowTo->scaffold('Reset A Password');" | head -3`
  → prints `---`, `title: Reset A Password`, `description: ...` with no leading
  whitespace.

### Step 2: Create the `MakeCommand`

Create `src/Console/MakeCommand.php`:
```php
<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use STS\Docent\Content\ContentType;
use STS\Docent\Sites\SiteRegistry;

/**
 * Scaffolds a new documentation page from a Diátaxis content-type template —
 * tutorial, how-to, reference, or concept — writing valid Docent dialect the
 * author (or a coding agent) fills in.
 */
final class MakeCommand extends Command
{
    protected $signature = 'docent:make
        {type : Content type: tutorial, how-to, reference, or concept}
        {slug : Page slug relative to the docs root, e.g. billing/refunds}
        {--site= : Scaffold into the selected Docent site}
        {--force : Overwrite an existing page}';

    protected $description = 'Scaffold a documentation page from a content-type template';

    public function handle(SiteRegistry $sites): int
    {
        $type = ContentType::tryFrom((string) $this->argument('type'));

        if ($type === null) {
            $this->components->error('Unknown content type. Use one of: '.implode(', ', ContentType::values()).'.');

            return self::FAILURE;
        }

        $selected = $this->option('site');

        if ($selected !== null && ! $sites->has($selected)) {
            $this->components->error('Unknown Docent site ['.$selected.'].');

            return self::FAILURE;
        }

        $docent = $sites->site($selected ?? $sites->defaultKey());
        $docs = (string) ($docent->config('filesystem.path') ?? resource_path('docs'));

        $slug = trim((string) $this->argument('slug'), '/');
        $slug = Str::endsWith($slug, '.md') ? Str::beforeLast($slug, '.md') : $slug;

        if ($slug === '') {
            $this->components->error('A slug is required.');

            return self::FAILURE;
        }

        $path = $docs.'/'.$slug.'.md';

        if (File::exists($path) && ! (bool) $this->option('force')) {
            $this->components->error($path.' already exists. Pass --force to overwrite.');

            return self::FAILURE;
        }

        $title = Str::headline(Str::afterLast($slug, '/'));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $type->scaffold($title)."\n");

        $this->components->twoColumnDetail($path, '<fg=green>created</>');
        $this->components->info('Scaffolded a '.$type->value.' page. Fill it in, then run `php artisan docent:check`.');

        return self::SUCCESS;
    }
}
```

**Verify**: `composer analyse` → exit 0.

### Step 3: Register the command

In `src/DocentServiceProvider.php`, add `MakeCommand::class` to the
`$this->commands([...])` array (line ~154) and add its `use` import with the
other `STS\Docent\Console\*` imports. Change ONLY that array line and the import
block. Example result:
```php
$this->commands([InstallCommand::class, ClearCommand::class, CheckCommand::class, GuideCommand::class, MakeCommand::class, PruneInsightsCommand::class]);
```

**Verify**: `vendor/bin/testbench list 2>/dev/null | grep docent:make` → shows the
command; `git diff src/DocentServiceProvider.php` shows ONLY the import + the one
array line changed (plus plan 008's pre-existing changes, which you did not
touch).

### Step 4: Tests

Create `tests/Feature/MakeCommandTest.php`. The command writes under the site's
`filesystem.path`; point that at a temp dir so tests don't pollute the repo, and
clean it up. Model command invocation after `tests/Feature/CommandsTest.php`.

Cover:
1. **Scaffolds each type and the result passes `docent:check`**: for `how-to`,
   point `docent.sites.docs.filesystem.path` at a fresh temp dir, run
   `docent:make how-to guides/reset-password`, assert the file exists and
   contains `title: Reset Password` and `## Goal`. Then run `docent:check` (or
   the `check()` helper if you reuse that fixture approach) against that temp dir
   and assert exit 0 — proving the scaffold is valid.
2. **Unknown type fails**: `docent:make nonsense foo` → exit non-zero, output
   mentions the valid types.
3. **Refuses to overwrite without --force**: create the page, run make again for
   the same slug → non-zero + "already exists"; then with `--force` → exit 0 and
   the file is rewritten.
4. **Unknown --site fails**: `docent:make concept foo --site=nope` → non-zero.

Use a temp directory via `sys_get_temp_dir().'/docent-make-'.uniqid()` created in
the test and removed in an `afterEach`/teardown. Do NOT write into
`resources/docs` or `tests/fixtures`.

**Verify**: `composer test` → all pass, including the new file.

## Test plan

- `tests/Feature/MakeCommandTest.php` (new): scaffold-and-check for one type,
  unknown-type failure, overwrite guard + `--force`, unknown-site failure.
- Structural patterns: `tests/Feature/CommandsTest.php` for install-style command
  tests; `tests/Feature/CheckCommandTest.php`'s `check()` helper if you validate
  the scaffold with `docent:check`.
- Verification: `composer test` → all pass.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0 with the new cases passing.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `vendor/bin/testbench docent:make how-to tmp/example` creates a file, and
      `docent:check` on a tree containing a scaffolded page passes (covered by the
      test).
- [ ] `src/Content/ContentType.php` and `src/Console/MakeCommand.php` exist.
- [ ] `git diff src/DocentServiceProvider.php` shows ONLY the `MakeCommand` import
      and its addition to the `commands([...])` array — nothing else.
- [ ] No plan 007–015 file other than that one `DocentServiceProvider.php` line is
      modified (`git status`).

## STOP conditions

Stop and report back if:

- Any Current-state excerpt doesn't match the live code beyond the expected
  007–015 batches (real drift).
- A scaffolded page FAILS `docent:check` (e.g. an `empty-steps` or
  `heading-hierarchy` finding) — that means a stub is malformed; report which
  rule fired rather than silently editing the stub in a way that changes its
  documented shape.
- Registering the command forces a change to `DocentServiceProvider.php` beyond
  the import + the one array line — report.
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: `docent:make` and its four type names become public API.
  Confirm the type set (the Diátaxis four) and the stub shapes read well — the
  stubs are opinionated templates and are the visible product of this command.
- Follow-ups (not now): add `quickstart` and `troubleshooting` types; surface the
  content-type shapes in `docent:guide` output so an agent knows they exist; a
  `--title` option to override the slug-derived title. Each is a small addition
  on this enum/command.
- If a stub ever needs a component that has its own check (cards, tabs), keep the
  block non-empty so `docent:check` stays green on a fresh scaffold.
