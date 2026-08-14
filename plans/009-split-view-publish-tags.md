# Plan 009: Split `docent-views` so only override-intended templates are a public surface

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/DocentServiceProvider.php`
> On any change, compare the "Current state" excerpt to the live code before
> proceeding; on a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none
- **Category**: tech-debt (semver hygiene)
- **Planned at**: commit `061a4c0`, 2026-07-20

## Why this matters

`vendor:publish --tag=docent-views` currently copies the *entire*
`resources/views` tree (~40 files) into the host app, including deep internal
partials (`partials/**`, `widget/**`, `partials/admin/**`,
`partials/ui-strings.blade.php`). Any host that publishes and keeps those files
pins their names, their `@include` graph, and their local variables. After a v1
tag, renaming or removing any internal partial silently breaks published copies
— even though the only contract we intend to promise is the *view-data payload*
(the keys passed to the layout), not 40 template files.

Splitting the publish tag now — before v1 — lets us promise a small,
override-intended set (the layout, page, landing, and public component
templates) under `docent-views`, while the internal partials publish only under
a separate, clearly-internal tag that we are free to restructure. This is a
config/registration change with no runtime behavior change.

## Current state

`src/DocentServiceProvider.php:138-146`:
```php
if ($this->app->runningInConsole()) {
    $this->publishes([__DIR__.'/../config/docent.php' => config_path('docent.php')], 'docent-config');
    $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/docent')], 'docent-views');
    $this->publishes([__DIR__.'/../lang' => lang_path('vendor/docent')], 'docent-lang');
    $this->publishes([__DIR__.'/../resources/dist' => public_path('vendor/docent')], 'docent-assets');
    $this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations')], 'docent-migrations');

    $this->commands([InstallCommand::class, ClearCommand::class, CheckCommand::class, GuideCommand::class, PruneInsightsCommand::class]);
}
```

The view tree (confirm with `find resources/views -maxdepth 2 -type f`):
- **Top-level, override-intended**: `layout.blade.php`, `page.blade.php`,
  `layouts/landing.blade.php`, `components/widget.blade.php`.
- **Internal partials/detail**: everything under `partials/`, `widget/`,
  `components/{hero,search-box,section-cards}.blade.php`, and any other nested
  template.

The documented-stable contract is the layout payload built in
`src/Http/Controllers/PageController.php:113-135` (keys `docent, siteName,
homeUrl, searchEnabled, assistantStateNamespace, page, context, title,
description, html, sections, topbarLinks, currentSlug`, plus landing extras).

**Convention to match**: Laravel supports multiple `publishes()` groups; a file
may belong to more than one tag. The idiom is one `publishes([...], 'tag')` call
per logical group. `vendor:publish` with no matching files for a tag is a no-op,
not an error.

## Commands you will need

| Purpose      | Command                                                   | Expected |
|--------------|-----------------------------------------------------------|----------|
| Tests        | `composer test`                                           | pass     |
| Lint         | `composer lint`                                           | exit 0   |
| Analyse      | `composer analyse`                                        | exit 0   |
| List views   | `find resources/views -type f -name '*.blade.php' \| sort` | file list |

## Scope

**In scope**:
- `src/DocentServiceProvider.php` (the `publishes()` calls only)

**Out of scope** (do NOT touch):
- Any `.blade.php` file — this plan does not move, rename, or edit templates.
  It only changes which publish tag exposes them.
- `resources/dist/**`, `config/docent.php`, `lang/**`, migrations — their tags
  are unchanged.
- `src/Console/InstallCommand.php` — the installer publishes `docent-config`
  only (verify with `grep -n "docent-" src/Console/InstallCommand.php`); it does
  not publish views, so it needs no change. If it DOES reference `docent-views`,
  STOP and report.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Enumerate the two view sets

Run `find resources/views -type f -name '*.blade.php' | sort` and split the
output into:
- **Public/override set**: `layout.blade.php`, `page.blade.php`,
  `layouts/landing.blade.php`, `components/widget.blade.php`.
- **Internal set**: every other file returned.

If the tree contains a top-level template not obviously in either bucket (e.g. a
new `layouts/<x>.blade.php`), treat layouts and `components/widget` as public and
everything else as internal; if still unsure, STOP and report the file.

### Step 2: Replace the single `docent-views` publish with two groups

Change the `docent-views` line in `src/DocentServiceProvider.php:140` so that:
- `docent-views` publishes ONLY the public/override set (individual file
  mappings), each into `resource_path('views/vendor/docent/...')` preserving
  the relative path.
- A new `docent-views-internal` tag publishes the WHOLE tree (so a host that
  truly needs to fork a partial still can, but via an explicitly-internal tag).

Target shape:
```php
$this->publishes([
    __DIR__.'/../resources/views/layout.blade.php'          => resource_path('views/vendor/docent/layout.blade.php'),
    __DIR__.'/../resources/views/page.blade.php'            => resource_path('views/vendor/docent/page.blade.php'),
    __DIR__.'/../resources/views/layouts/landing.blade.php' => resource_path('views/vendor/docent/layouts/landing.blade.php'),
    __DIR__.'/../resources/views/components/widget.blade.php' => resource_path('views/vendor/docent/components/widget.blade.php'),
], 'docent-views');

$this->publishes([
    __DIR__.'/../resources/views' => resource_path('views/vendor/docent'),
], 'docent-views-internal');
```
Keep the four surrounding `publishes()`/`publishesMigrations()` calls exactly as
they are.

**Verify**: `composer analyse` → exit 0; `composer test` → all pass (nothing in
the suite depends on publish granularity, but confirm).

### Step 3: Prove the tags resolve to the intended files

The package's own tests run under Orchestra Testbench, so `vendor:publish` is
available. Confirm the split with a dry-run listing (Testbench exposes the
package's tags):

```bash
vendor/bin/testbench vendor:publish --tag=docent-views --help >/dev/null 2>&1; echo "views tag exit: $?"
```
This only confirms the command accepts the tag. For the actual file set, rely on
the grep in Done criteria rather than a real publish (a real publish writes into
the testbench skeleton and is unnecessary).

**Verify**:
- `grep -c "docent-views'" src/DocentServiceProvider.php` → 1 (the public tag).
- `grep -c "docent-views-internal'" src/DocentServiceProvider.php` → 1.
- `grep -c "resources/views' =>" src/DocentServiceProvider.php` → 1 (only the
  internal tag maps the whole directory now).

## Test plan

No new tests. This is a registration change with no runtime effect on rendering.
Guards:
- `composer test` — the full suite (renders pages via the package's own views,
  which load from `loadViewsFrom`, unaffected by publish tags) must stay green.
- `composer analyse` — confirms the array shape is valid.

Optional (reviewer may ask for it): a Testbench feature test asserting
`$this->artisan('vendor:publish', ['--tag' => 'docent-views'])` succeeds and does
NOT create `resources/views/vendor/docent/partials/...`. Only add if the reviewer
requests it — publishing into the skeleton can leave artifacts that other tests
notice, so it is deferred by default.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0.
- [ ] `docent-views` maps only the 4 public templates (no `resources/views =>`
      whole-directory mapping under that tag).
- [ ] `docent-views-internal` exists and maps the whole `resources/views` tree.
- [ ] The other four publish groups (`docent-config`, `docent-lang`,
      `docent-assets`, `docent-migrations`) are unchanged
      (`git diff src/DocentServiceProvider.php` shows only the views block moved).
- [ ] No `.blade.php` file is modified (`git status` lists no template files).

## STOP conditions

Stop and report back if:

- The "Current state" excerpt doesn't match (drift since `061a4c0`).
- `find resources/views` reveals a top-level template you can't confidently
  place in the public vs internal bucket.
- `InstallCommand` or any test references the `docent-views` tag in a way that
  assumes it publishes the whole tree.
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: confirm the public set is exactly the templates a host would
  legitimately override for branding (layout, page, landing, widget). Anything
  else moving into `docent-views` re-creates the problem this plan fixes.
- This pairs with plan 013 (documenting the v1 API stance): the docs should state
  that only `docent-views` templates + the layout payload keys are covered by
  semver; `docent-views-internal` is publish-at-your-own-risk.
- When a new top-level, override-worthy template is added later, add it to the
  `docent-views` mapping explicitly — new templates default to internal-only
  otherwise, which is the safe direction.
