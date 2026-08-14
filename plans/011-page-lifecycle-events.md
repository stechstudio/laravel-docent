# Plan 011: Dispatch page lifecycle events (saved / published / unpublished / deleted)

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving on. If a
> "STOP conditions" item occurs, stop and report — do not improvise. Do NOT
> update `plans/README.md` or commit — a reviewer maintains the index and git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Content/Models/DocentPage.php`
> On any change, compare the "Current state" excerpts to the live code first; on
> a mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW
- **Depends on**: run AFTER plan 010 (models lose `final`) if both are in the same
  batch — not a hard dependency, but 010 touches the same file's class line and
  doing 010 first avoids a merge conflict on `DocentPage.php`.
- **Category**: direction (extension point) / tech-debt
- **Planned at**: commit `061a4c0`, 2026-07-20

## Why this matters

The package dispatches no events. Host apps commonly need to react to
documentation changes: reindex an external search engine, invalidate a CDN,
write an audit-log entry, notify a channel. Today they have no hook and would
have to override controllers or models to intercept saves. Adding events later
is technically non-breaking, but the event *names and payloads* become a
promise the first time someone listens — so getting a minimal, well-named set
into v1 means the extension point exists from day one and its shape is
considered rather than rushed.

The maintainer approved shipping a minimal set: **PageSaved**, **PagePublished**,
**PageUnpublished**, **PageDeleted**. All four are dispatched from the model —
the single funnel every write path already flows through — so every caller
(both admin controllers and the `Editor` service) gets them for free, and a host
subclass (see plan 010) inherits the behavior.

## Current state

Every database-page mutation funnels through `src/Content/Models/DocentPage.php`:

- `write()` (static, `src/Content/Models/DocentPage.php:52-99`) is the single
  create/update path — the two admin controllers and `Editor` all call it, and
  `revertTo()` (line 136) calls it internally. It ends with:
  ```php
      $page->save();

      if ($changed) {
          $page->revisions()->create([...]);
          $page->unsetRelation('revisions');
      }

      return $page;
  ```
- `publish()` (`:105-113`) sets `published_revision_id` and saves.
- `unpublish()` (`:115-121`) nulls it and saves.
- Deletion happens via `->delete()` (soft delete; `DocentPage` uses
  `SoftDeletes`) from `src/Http/Controllers/Admin/PageController.php:93` and
  `src/Admin/Editor.php:261`.

Call sites confirming the model is the funnel:
- `src/Http/Controllers/Admin/PageController.php:48` and `:76` — `DocentPage::write(...)`.
- `src/Http/Controllers/Admin/PageStateController.php:25` — `->publish()`;
  `:34` — `->unpublish()`; `:47` — `->revertTo()` (→ `write()`).
- `src/Admin/Editor.php:243`, `:283` — `DocentPage::write(...)`; `:261` — `->delete()`.

There is no `src/Content/Events/` directory yet (confirm:
`ls src/Content/Events 2>/dev/null` → absent).

**Convention to match**: subsystem-namespaced classes (`STS\Docent\Content\...`),
`final` classes, constructor property promotion, `declare(strict_types=1)`. Use
Laravel's `event()` helper to dispatch and the framework's `Dispatchable` trait
on the event classes (matches Laravel idiom; no custom dispatcher). For the
delete hook, use Eloquent's model-event map `$dispatchesEvents` rather than a
manual dispatch, since deletion doesn't go through a single domain method.

## Commands you will need

| Purpose   | Command             | Expected            |
|-----------|---------------------|---------------------|
| Tests     | `composer test`     | all pass, exit 0    |
| Lint      | `composer lint`     | exit 0              |
| Analyse   | `composer analyse`  | exit 0, no errors   |

## Scope

**In scope**:
- `src/Content/Events/PageSaved.php` (create)
- `src/Content/Events/PagePublished.php` (create)
- `src/Content/Events/PageUnpublished.php` (create)
- `src/Content/Events/PageDeleted.php` (create)
- `src/Content/Models/DocentPage.php` (dispatch from `write`/`publish`/`unpublish`;
  add `$dispatchesEvents` for `deleted`)
- `tests/Feature/PageLifecycleEventsTest.php` (create)

**Out of scope** (do NOT touch):
- The admin controllers and `Editor` — they call the model; no changes needed.
- `DocentPageRevision`, `AiQuestion`, `InsightEvent` — no events for these in v1.
- Any config toggle for events — events with no listeners are free; ship them
  unconditionally.
- Adding listeners inside the package — we dispatch; hosts listen.

## Git workflow

- Do NOT commit, branch, or push. Leave changes in the working tree for review.

## Steps

### Step 1: Create the four event classes

Each is a `final` class carrying the affected page. `PageSaved` also carries
whether the row was newly created. Example — `src/Content/Events/PageSaved.php`:

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Content\Events;

use Illuminate\Foundation\Events\Dispatchable;
use STS\Docent\Content\Models\DocentPage;

/**
 * A database-authored page was created or updated (a revision may have been
 * snapshotted). `$created` is true only on the first save of a new page.
 */
final class PageSaved
{
    use Dispatchable;

    public function __construct(
        public readonly DocentPage $page,
        public readonly bool $created,
    ) {}
}
```

`PagePublished`, `PageUnpublished`, and `PageDeleted` follow the same shape with
just `public readonly DocentPage $page` (no `$created`). Give each a one-line
docblock describing when it fires.

**Verify**: `composer analyse` → exit 0 (confirms the classes are well-formed and
`DocentPage` imports resolve).

### Step 2: Dispatch from the domain methods

In `src/Content/Models/DocentPage.php`:

- At the end of `write()`, before `return $page;`, capture whether it was newly
  created and dispatch:
  ```php
  PageSaved::dispatch($page, $page->wasRecentlyCreated);

  return $page;
  ```
  (`wasRecentlyCreated` is set by Eloquent's `save()` to true only on insert.)

- At the end of `publish()`, before `return $this;`:
  ```php
  PagePublished::dispatch($this);
  ```

- At the end of `unpublish()`, before `return $this;`:
  ```php
  PageUnpublished::dispatch($this);
  ```

Add the three `use STS\Docent\Content\Events\...;` imports.

**Verify**: `grep -c "::dispatch(" src/Content/Models/DocentPage.php` → 3.

### Step 3: Map the delete event via `$dispatchesEvents`

Deletion is not a single domain method, so hook Eloquent's model event. Add to
`DocentPage`:
```php
/** @var array<string, class-string> */
protected $dispatchesEvents = [
    'deleted' => PageDeleted::class,
];
```
Eloquent constructs `PageDeleted` with the model instance, which matches the
`public readonly DocentPage $page` constructor. This fires on soft delete (the
model uses `SoftDeletes`), which is the actual admin behavior. Add the
`use ... PageDeleted;` import.

**Verify**: `grep -n "dispatchesEvents" src/Content/Models/DocentPage.php` → one
match; `composer analyse` → exit 0.

### Step 4: Test all four events fire

Create `tests/Feature/PageLifecycleEventsTest.php`. Use `Event::fake([...])` for
the four event classes, exercise each mutation via the model API, and assert the
event dispatched with the expected payload. Model the DB setup after an existing
feature test that writes `DocentPage` rows (see
`tests/Feature/DatabasePageAuthorizationTest.php` for how the suite provisions
database pages and the connection/migration setup it relies on).

Cover:
- `DocentPage::write(...)` on a new slug → `PageSaved` with `created === true`.
- `write(...)` again on the same slug → `PageSaved` with `created === false`.
- `->publish()` → `PagePublished`.
- `->unpublish()` → `PageUnpublished`.
- `->delete()` → `PageDeleted`.

Shape:
```php
use STS\Docent\Content\Events\PageSaved;
use STS\Docent\Content\Models\DocentPage;
use Illuminate\Support\Facades\Event;

it('dispatches PageSaved with created=true for a new page', function () {
    Event::fake([PageSaved::class]);

    DocentPage::write('guides/intro', '# Intro', ['title' => 'Intro']);

    Event::assertDispatched(PageSaved::class, fn (PageSaved $e) => $e->created === true
        && $e->page->slug === 'guides/intro');
});
```

**Verify**: `composer test` → all pass, including the new file's cases.

## Test plan

- `tests/Feature/PageLifecycleEventsTest.php` (new): five assertions — the two
  `PageSaved` variants (created true/false), `PagePublished`, `PageUnpublished`,
  `PageDeleted` — each checking the event dispatched and its `page` payload.
- Structural pattern: `tests/Feature/DatabasePageAuthorizationTest.php` for DB
  provisioning; `Event::fake`/`assertDispatched` is standard Laravel.
- Verification: `composer test` → all pass.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0; `tests/Feature/PageLifecycleEventsTest.php` exists
      and passes.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] Four files exist under `src/Content/Events/`
      (`ls src/Content/Events` → PageSaved, PagePublished, PageUnpublished,
      PageDeleted).
- [ ] `grep -c "::dispatch(" src/Content/Models/DocentPage.php` → 3.
- [ ] `grep -n "dispatchesEvents" src/Content/Models/DocentPage.php` → one match.
- [ ] No files outside the in-scope list are modified (`git status`).

## STOP conditions

Stop and report back if:

- "Current state" excerpts don't match live code (drift since `061a4c0`).
- `wasRecentlyCreated` is not available on the page after `save()` in this
  Laravel version (the created/updated distinction test fails) — report so the
  reviewer can pick another signal (e.g. `$page->wasChanged()` vs `exists`
  captured before save).
- Exercising `->delete()` in the test dispatches `PageDeleted` more than once or
  not at all (a `SoftDeletes` vs `forceDelete` subtlety) — report the observed
  behavior.
- A verification fails twice after a reasonable fix attempt.

## Maintenance notes

- For the reviewer: the payload is deliberately just the model (plus `created`
  for saved). Adding fields later is non-breaking; removing them is not — so
  keep payloads minimal. Confirm no event carries a revision or user object that
  we'd regret freezing.
- These four names are now public API. Plan 013 (API stance docs) should list
  them under "what v1 covers."
- If a future feature adds hard deletes or bulk operations, verify `PageDeleted`
  still fires as expected (bulk `delete()` on a query builder bypasses model
  events — a documented Eloquent caveat worth a note if bulk delete is added).
- Deferred: events for revisions, AI questions, or insight events — not part of
  the approved v1 set.
