# Plan 017: Page-backed expanding sidebar groups (index.md-derived)

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/README.md` — unless a reviewer dispatched you and told you they
> maintain the index.
>
> **Drift check (run first)**:
> `git diff --stat 10dbdf3..HEAD -- src/Navigation/ src/Content/AgentFeed.php src/DocentManager.php resources/views/partials/nav-node.blade.php resources/views/widget/nav-node.blade.php lang/en/ui.php tests/Feature/NavigationTest.php tests/fixtures/docs/`
> If any in-scope file changed since this plan was written, compare the
> "Current state" excerpts against the live code before proceeding; on a
> mismatch, treat it as a STOP condition.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: MED (the `flatten()` change feeds llms.txt, prev/next, and section URLs)
- **Depends on**: none
- **Category**: direction (feature)
- **Planned at**: commit `10dbdf3`, 2026-07-24

## Why this matters

Docent's sidebar has collapsible sub-groups, but a group header is a **pure
toggle**: clicking it only expands/collapses, even when the directory has an
`index.md` landing page. Reference docs sites (Cloudflare's Nimbus-built dev
docs, Mintlify) make a collapsible section header **navigable** when the
section has a landing page: clicking the label navigates to it AND the section
expands; a separate chevron toggles without navigating. Docent should offer
both modes — and it can derive the mode from the filesystem it already has,
with zero new authoring surface: a directory **with** `index.md` gets a
page-backed header; a directory **without** one keeps today's toggle-only
header. The index page also stops appearing redundantly as the first child
item inside its own group.

## Current state

Files and their roles:

- `src/Navigation/NavigationGroup.php` — the sidebar group value object.
- `src/Navigation/NavigationBuilder.php` — builds the tree; `filterGroup()`
  constructs `NavigationGroup`s, `flatten()` linearizes for prev/next and the
  llms feeds, `groupCard()` builds section cards.
- `src/Navigation/NavigationItem.php` — leaf item; has
  `active(string $currentSlug): bool` (`slug === currentSlug`).
- `src/DocentManager.php:756-765` — `breadcrumb()` uses
  `NavigationGroup::contains()`.
- `src/Content/AgentFeed.php:88,107` — llms.txt / llms-full.txt call
  `$this->navigation->flatten(...)`; whatever `flatten()` omits silently
  disappears from the agent feeds.
- `resources/views/partials/nav-node.blade.php` — sidebar rendering (three
  cases: top-level section, nested collapsible group, leaf).
- `resources/views/widget/nav-node.blade.php` — help-widget sidebar; iterates
  `$node->items` / `$node->groups` directly.
- `lang/en/ui.php` — translated UI strings (`docent::ui.*` namespace).
- `tests/Feature/NavigationTest.php`, `tests/Feature/NavigationSectionsTest.php`,
  `tests/Feature/AgentDocsTest.php`, `tests/Unit/SectionCardsTest.php` — the
  affected test surface.
- `tests/fixtures/docs/` — navigation fixture tree. `guides/` has an
  `index.md` (slug `guides`, title "Guides Overview", `order: 0`); `billing/`
  and `reports/` — `reports/` has `index.md`, `billing/` does not.

How an index page flows through the tree today: `billing/index.md` produces a
`PageReference` with `slug: 'billing'` and `directory: 'billing'`
(see the docblock in `src/Content/PageReference.php:8-13` — index pages
collapse their slug). `NavigationBuilder::build()` files every page into its
directory's node, so the index page sits in the group's `items` list and
renders as an ordinary leaf inside its own collapsible group.

`src/Navigation/NavigationGroup.php` (whole class, current; docblocks
omitted here for brevity — the live file has a class docblock and a
`contains()` docblock, and that is not drift):

```php
final class NavigationGroup
{
    /**
     * @param  list<NavigationItem>  $items
     * @param  list<NavigationGroup>  $groups
     */
    public function __construct(
        public readonly string $label,
        public readonly ?string $icon = null,
        public readonly array $items = [],
        public readonly array $groups = [],
    ) {}

    public function contains(string $slug): bool
    {
        foreach ($this->items as $item) {
            if ($item->slug === $slug) {
                return true;
            }
        }

        foreach ($this->groups as $group) {
            if ($group->contains($slug)) {
                return true;
            }
        }

        return false;
    }
}
```

`src/Navigation/NavigationBuilder.php:447-472` — `filterGroup()` (current):

```php
private function filterGroup(array $node, DocumentationContext $context): ?NavigationGroup
{
    $items = [];

    foreach ($this->sortPages($node['items']) as $page) {
        if ($this->visible($page, $context)) {
            $items[] = $this->item($page);
        }
    }

    $groups = [];

    foreach ($this->sortGroups($node['children']) as $child) {
        $group = $this->filterGroup($child, $context);

        if ($group !== null) {
            $groups[] = $group;
        }
    }

    if ($items === [] && $groups === []) {
        return null;
    }

    return new NavigationGroup($node['label'], $node['icon'], $items, $groups);
}
```

Note: `$node['directory']` holds the group's accumulated directory path
(e.g. `guides` or `getting-started/deploy`) — this is what the index page's
slug equals.

`src/Navigation/NavigationBuilder.php:579-592` — `flatten()` (current):

```php
public function flatten(array $nodes): array
{
    $items = [];

    foreach ($nodes as $node) {
        if ($node instanceof NavigationItem) {
            $items[] = $node;
        } else {
            array_push($items, ...$node->items, ...$this->flatten($node->groups));
        }
    }

    return $items;
}
```

`src/Navigation/NavigationBuilder.php:310-336` — `groupCard()` currently
finds the index by scanning items:

```php
$pages = $this->flatten([$group]);
$index = null;

foreach ($group->items as $item) {
    if ($item->slug === $node['directory']) {
        $index = $item;
        break;
    }
}

return new SectionCard(
    $node['directory'],
    $node['label'],
    $index->url ?? $pages[0]->url,
    ($node['description'] ?? null) ?? $index?->description,
    $node['icon'],
    count($pages),
);
```

`resources/views/partials/nav-node.blade.php` — the nested-group case
(lines 21-42, current). The whole header row is one toggle `<button>`:

```blade
@elseif($isGroup)
    {{-- Nested, collapsible sub-section. --}}
    <li x-data="{ open: {{ $node->contains($currentSlug) ? 'true' : 'false' }} }">
        <button type="button" @click="open = !open" :aria-expanded="open"
                class="flex w-full items-center justify-between rounded-md px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">
            <span class="flex items-center gap-1.5">
                @if($node->icon && ($groupIcon = \STS\Docent\Support\Icon::svg($node->icon)))
                    <span class="inline-flex text-[var(--docent-faint)] [&_svg]:h-3.5 [&_svg]:w-3.5" aria-hidden="true">{!! $groupIcon !!}</span>
                @endif
                <span>{{ $node->label }}</span>
            </span>
            <svg class="transition-transform duration-150" :class="open && 'rotate-90'" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
        <ul x-show="open" x-collapse.duration.150ms class="mt-0.5 space-y-0.5 border-l border-slate-200 pl-2 dark:border-slate-800">
            ...items/groups loops...
        </ul>
    </li>
```

The top-level case (lines 3-20) renders a `<p>` header and loops
`$node->items` then `$node->groups`. The leaf case (lines 43-53) renders an
`<a>` with active styling:

```blade
@php($active = $node->active($currentSlug))
<li class="docent-nav-item{{ $active ? ' is-active' : '' }}">
    <a href="{{ $node->url }}" @if($active) aria-current="page" @endif
       class="block rounded-md px-3 py-1.5 text-sm transition {{ $active
           ? 'bg-[color-mix(in_srgb,var(--docent-accent)_12%,transparent)] font-medium text-[var(--docent-accent)]'
           : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
        {{ $node->title }}
    </a>
</li>
```

Repo conventions that apply:

- Value objects are `final` with `public readonly` promoted constructor
  properties and full generic docblocks (see `NavigationGroup`,
  `NavigationItem`). PHPStan runs at a strict level — keep `@param` list
  types accurate.
- Pest tests, plain `it(...)` functions; model new tests after
  `tests/Feature/NavigationTest.php` and `tests/Feature/NavigationSectionsTest.php`.
- Translated UI strings live in `lang/en/ui.php` under the `docent::ui.*`
  namespace, referenced as `__('docent::ui.common.open_navigation')` etc.
- Commit messages: conventional prefix, lowercase imperative
  (e.g. `feat: surface docent:make and JSON check at install time`).
  **Never add Co-Authored-By or "Generated with" lines.**

## Commands you will need

| Purpose   | Command            | Expected on success |
|-----------|--------------------|---------------------|
| Tests     | `composer test`    | exit 0, all pass    |
| Lint      | `composer lint`    | exit 0              |
| Static    | `composer analyse` | exit 0, no errors   |
| Smoke     | `vendor/bin/testbench docent:check` | exit 0 |

(All verified working at the planned-at commit. There is NO root `artisan`
in this package repo — use `vendor/bin/testbench` for artisan commands.)

## Scope

**In scope** (the only files you should modify):

- `src/Navigation/NavigationGroup.php`
- `src/Navigation/NavigationBuilder.php`
- `resources/views/partials/nav-node.blade.php`
- `resources/views/widget/nav-node.blade.php`
- `lang/en/ui.php`
- `tests/Feature/NavigationTest.php` (update expectations)
- `tests/Feature/NavigationRenderingTest.php` (create)
- `tests/fixtures/docs/**` (only if a fixture addition is needed — see Step 5)
- `plans/README.md` (status row only)

**Out of scope** (do NOT touch, even though they look related):

- `src/DocentManager.php` — `breadcrumb()` keeps working unchanged because
  `contains()` is updated to cover the index slug.
- `src/Content/AgentFeed.php` — must keep working unchanged via the
  `flatten()` fix; if it needs edits, something is wrong (STOP).
- `src/Navigation/NavigationSection.php`, `NavigationLink.php`,
  `SectionCard.php` — shapes unchanged.
- Top-level `section: true` behavior, `NavigationSectionCheck`, the section
  switcher — unrelated.
- Any new `_group.yml` key (e.g. `collapsed:`) — explicitly deferred.
- CSS/JS source or `resources/dist/*`.
- Pre-existing dirty working-tree files listed in `plans/README.md`
  "Global executor rules".

## Git workflow

- Branch off `main`: `feature/017-page-backed-groups` (or work in the
  dispatched worktree if a reviewer set one up).
- One commit at the end is fine; message like
  `feat: page-backed expanding sidebar groups from index.md`.
- Do NOT push. Never commit the `plans/` directory.

## Steps

### Step 1: Give `NavigationGroup` an index item

In `src/Navigation/NavigationGroup.php`:

1. Append a new **last** constructor parameter
   `public readonly ?NavigationItem $index = null` (last position keeps every
   existing positional call site compatible). Document it: the group
   directory's own `index.md` page, when one exists and is visible to the
   viewer; the sidebar renders the group header as a link to it.
2. Update `contains()` to also return true when
   `$this->index?->slug === $slug`. (This keeps auto-expansion and
   `DocentManager::breadcrumb()` working when the current page IS the
   group's landing page.)
3. Update the class docblock `@param` list accordingly.

**Verify**: `composer analyse` → exit 0 (nothing else updated yet, so no
callers break — the param is optional).

### Step 2: Promote the index in `NavigationBuilder`

All in `src/Navigation/NavigationBuilder.php`:

1. **`filterGroup()`**: after building the filtered `$items` list, extract
   the index item — the one whose `slug === $node['directory']` — into a
   local `$index` (remove it from `$items`; there is at most one). Change the
   empty check to `if ($index === null && $items === [] && $groups === [])`
   and pass `$index` as the new last constructor argument:

   ```php
   return new NavigationGroup($node['label'], $node['icon'], $items, $groups, $index);
   ```

   Extraction happens *after* the `visible()` filtering loop, so a gated
   index page (e.g. `authorize:` the viewer fails) simply yields
   `$index === null` and the group falls back to a toggle-only header —
   permission-awareness for free.

2. **`flatten()`**: include the index FIRST when present. Target shape:

   ```php
   } else {
       if ($node->index !== null) {
           $items[] = $node->index;
       }

       array_push($items, ...$node->items, ...$this->flatten($node->groups));
   }
   ```

   This is load-bearing: `flatten()` feeds prev/next, section URLs
   (`section()` uses `flatten($navigation)[0]->url`), and both llms feeds in
   `src/Content/AgentFeed.php`. Omitting it silently drops every index page
   from llms.txt. Behavior note: the index is now pinned first in reading
   order regardless of its `order:` front matter — intentional (a landing
   page reads first).

3. **`groupCard()`**: replace the `foreach ($group->items ...)` index-scan
   (shown in Current state) with `$index = $group->index;`. Everything else
   in the method stays as is.

**Verify**: `composer test` → the suite runs; expect failures ONLY in
`tests/Feature/NavigationTest.php` (assertions that list "Guides Overview"
among `$guides->items` — fixed in Step 5). If `AgentDocsTest`,
`NavigationSectionsTest`, or `SectionCardsTest` fail, your `flatten()` or
`groupCard()` change is wrong — fix before proceeding.

### Step 3: Render the split header in the docs sidebar

In `resources/views/partials/nav-node.blade.php`:

1. **Nested-group case** (`@elseif($isGroup)`): branch on `$node->index`.

   - **With an index** — render a header row `<div class="flex items-center">`
     containing (a) an `<a href="{{ $node->index->url }}">` carrying the icon
     + `$node->label`, with active styling and `aria-current="page"` when
     `$node->index->active($currentSlug)` (reuse the leaf case's active
     classes for consistency), and (b) a separate chevron
     `<button type="button" @click="open = !open" :aria-expanded="open"
     aria-label="{{ __('docent::ui.common.toggle_section', ['section' => $node->label]) }}">`
     with the existing chevron `<svg>` and rotate binding. The label link
     must NOT toggle; the chevron must NOT navigate.
   - **Without an index** — keep today's whole-row `<button>` exactly as is.
   - **Edge case**: `$node->index` present but `$node->items === [] &&
     $node->groups === []` (the only visible page is the landing page) —
     render just the header link, no chevron, no `<ul>`, no `x-data`.
   - The `x-data="{ open: ... }"` initialization is unchanged; because
     `contains()` now matches the index slug, landing on the index page
     auto-expands the group after navigation.

2. **Top-level section case** (`$isGroup && ! $nested`): the header `<p>`
   stays a non-link, but the extracted index must not vanish — render it as
   the FIRST entry inside the `<ul>`, before the items loop:

   ```blade
   @if($node->index)
       @include('docent::partials.nav-node', ['node' => $node->index, 'nested' => true])
   @endif
   ```

   (This preserves today's visual for top-level groups, e.g. "Guides
   Overview" as first item under the GUIDES header.)

3. Add the translation key in `lang/en/ui.php` under `'common'`:
   `'toggle_section' => 'Toggle :section',` — matching the style of the
   existing `open_navigation` / `close_navigation` keys.

**Verify**: `composer lint` → exit 0. Rendering assertions come in Step 5.

### Step 4: Keep the help widget complete

In `resources/views/widget/nav-node.blade.php`, the group case iterates
`$node->items` directly, so the extracted index would disappear from the
widget sidebar. The widget has no collapse behavior — just render the index
as the first leaf inside the group's `<ul>`, before the items loop:

```blade
@if($node->index)
    @include('docent::widget.nav-node', ['node' => $node->index, 'depth' => $depth + 1])
@endif
```

**Verify**: `composer test -- --filter=Widget` → all pass.

### Step 5: Tests

1. **Update `tests/Feature/NavigationTest.php`** for the promotion. The
   fixture `guides/index.md` (slug `guides`, "Guides Overview") is no longer
   in `$guides->items`:
   - "orders pages within a group by front matter order then title": items
     become `['Setup', 'Cycle']`; add
     `expect($guides->index?->title)->toBe('Guides Overview')`.
   - "excludes hidden pages from navigation": unchanged assertion still
     holds; leave it.
   - "filters unauthorized pages and empty groups per viewer": `billing/`
     has NO index.md — assert `$billing->index)->toBeNull()` alongside the
     existing items expectations (which are unchanged).
   - "computes prev/next from the flattened filtered navigation": must still
     pass unchanged (`prev` of `guides/setup` is `guides`) — it now comes
     from the pinned-first index. Do not weaken it.
2. **New assertions in `NavigationTest.php`** (same file, new `it(...)`):
   - `contains()` matches the index slug:
     `findGroup(docentNav($this), 'Guides')->contains('guides')` is true.
   - A gated index, using `reports`: `tests/fixtures/docs/reports/index.md`
     carries `authorize: reports.view`, and it is the group's ONLY page. For
     a guest the index filters out, the group is empty, and
     `findGroup(guestNav, 'Reports')` is null (already asserted elsewhere);
     for the admin, `findGroup(adminNav, 'Reports')->index` is not null and
     `->items` is `[]`. Two notes so you don't second-guess this: (a)
     `reports/_group.yml` has `section: true`, but that only matters to
     `sections()` — the `navigation()` call used by `docentNav()` returns
     the full `filtered()` tree, so Reports appears there as a normal group;
     (b) admin Reports is exactly the Step 3 "index present, no other
     children" edge case (header link, no chevron) — the render side of that
     edge is nested-group-only, so this unit assertion is its coverage here.
3. **Create `tests/Feature/NavigationRenderingTest.php`** — HTTP-level
   render assertions, modeled structurally on
   `tests/Feature/NavigationSectionsTest.php`'s last test ("renders visible
   section switches..."). The fixture tree has no *nested* group (a
   directory inside a directory), which is the only place the collapsible
   header renders — **add one**: create
   `tests/fixtures/docs/guides/deploy/production.md` (front matter: title
   `Production`, order 1) and `tests/fixtures/docs/guides/deploy/index.md`
   (title `Deploy Overview`, order 0), plus a second nested dir WITHOUT an
   index, `tests/fixtures/docs/guides/troubleshooting/faq.md` (title `FAQ`).
   Note that `guides/` itself is a TOP-LEVEL group, so despite having an
   `index.md` it renders the always-open `<p>` header with the index pinned
   as first item (Step 3.2), never the split header — that's why the split
   header assertions below target the nested `deploy` group, not `guides`.
   The `Troubleshooting` label comes from `Str::headline('troubleshooting')`
   (no `_group.yml` needed — `NavigationBuilder::build()` falls back to the
   headline). `tests/Feature/AgentDocsTest.php` uses `toContain` and
   relative-position assertions, not full-content equality, and the new
   fixture titles collide with none of them, so it should stay green; if
   adding fixture pages does break an unrelated assertion anyway, update
   that expectation to include the new pages rather than weakening it. Then
   assert on `GET /docs/guides/setup`:
   - the page-backed header renders a link:
     `->assertSee('href="http://localhost/docs/guides/deploy"', false)`;
   - the toggle-only header renders no link for troubleshooting (no
     `href=".../docs/guides/troubleshooting"`) but does render its label
     `Troubleshooting`;
   - the chevron button's accessible name renders:
     `->assertSee('Toggle Deploy', false)` (via the aria-label).
   And on `GET /docs/guides/deploy` (the landing page itself):
   - `->assertSee('aria-current="page"', false)` and the group's child
     `Production` is present in the HTML (auto-expanded via `contains()`).
4. Re-run the full gates.

**Verify**: `composer test` → exit 0, all pass including the new file.
`composer lint` → exit 0. `composer analyse` → exit 0.

### Step 6: Smoke

`vendor/bin/testbench docent:check` → exit 0 (the workbench corpus still
validates; no navigation-section errors introduced).

## Test plan

Covered in Step 5. Summary of the cases that must be proven:

- Index promotion: `$group->index` set when `index.md` exists, null when not.
- Items list no longer duplicates the index page.
- `contains()` matches the index slug (auto-expand + breadcrumb).
- `flatten()` keeps index pages (prev/next test unchanged; `AgentDocsTest`
  llms tests unchanged and green — they are the regression net for the feeds).
- Rendered HTML: page-backed header is a link with a separate labeled toggle;
  index-less header is not a link; active landing page gets `aria-current`
  and an expanded child list.
- Widget: index page still listed (existing Widget tests plus the Step 4
  filter run).

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0; `tests/Feature/NavigationRenderingTest.php`
      exists and its tests pass
- [ ] `composer lint` exits 0
- [ ] `composer analyse` exits 0
- [ ] `vendor/bin/testbench docent:check` exits 0
- [ ] `grep -n "index" src/Navigation/NavigationGroup.php` shows the new
      constructor property and the `contains()` index check
- [ ] `grep -c "item->slug === \$node\['directory'\]" src/Navigation/NavigationBuilder.php`
      returns 0 (the `groupCard()` scan is gone)
- [ ] `git status` shows no modified files outside the in-scope list
      (pre-existing dirty files from `plans/README.md` global rules excepted)
- [ ] `plans/README.md` status row for 017 updated

## STOP conditions

Stop and report back (do not improvise) if:

- The excerpts in "Current state" don't match the live code (drift).
- `tests/Feature/AgentDocsTest.php` fails after Step 2 and the fix isn't a
  bug in your `flatten()` edit — the llms feed contract must not change; do
  not edit `src/Content/AgentFeed.php` to make it pass.
- Preserving prev/next behavior (`prev` of `guides/setup` === `guides`)
  would require changing `prevNext()` itself.
- You find more than one item in a group whose slug equals the group
  directory (the "at most one index" assumption is false).
- The widget or docs sidebar needs Alpine/JS changes beyond the Blade
  templates (e.g. edits under `resources/js/` or `resources/dist/`).
- Adding the nested-group fixtures breaks more than ~3 unrelated tests —
  the fixture tree is more load-bearing than this plan assumed.

## Maintenance notes

- **Public API**: `NavigationGroup` gained an optional trailing constructor
  param and `contains()` now matches the index slug. Additive; existing
  positional construction is unaffected. If COMPATIBILITY.md enumerates the
  navigation value objects, no edit is needed (additive change), but the
  reviewer should double-check.
- **Behavior change to call out in the changelog**: a group's `index.md` no
  longer appears as a child item; its group header becomes the link (nested
  groups) or it is pinned as the first entry (top-level groups) — and it is
  pinned first in reading order (prev/next, llms feeds) regardless of
  `order:` front matter.
- **Deferred, intentionally**: a `collapsed: true` `_group.yml` key for
  default-collapsed groups; making top-level `<p>` section headers
  link-capable or collapsible. Neither is wanted yet.
- **Docs follow-up (separate repo, not this plan)**: document the behavior
  in docent-docs `authoring/organization.md` (a directory with `index.md`
  gets a navigable expanding header; without one, a toggle-only header).
- **Review focus**: the `flatten()` edit (feeds llms.txt), the Blade split
  header's accessibility (link vs button roles, `aria-expanded`,
  `aria-label`), and that the two nav-node templates (docs + widget) both
  still surface every visible page exactly once.
