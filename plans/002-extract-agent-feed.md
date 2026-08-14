# Plan 002: Extract the LLM/agent feed cluster from DocentManager into `STS\Docent\Content\AgentFeed`

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/README.md` (do not commit the `plans/` directory).
>
> **Drift check (run first)**: this plan was written at commit `a95a36a` but MUST run
> after plan 001. Verify plan 001's status is DONE in `plans/README.md`, then confirm
> the excerpts below still match `src/DocentManager.php` (line numbers will have
> shifted after 001 — match by method name, not line).

## Status

- **Priority**: P2
- **Effort**: S
- **Risk**: LOW
- **Depends on**: plans/001-extract-admin-editor.md
- **Category**: tech-debt
- **Planned at**: commit `a95a36a`, 2026-07-18

## Why this matters

After plan 001, `DocentManager` still bundles a self-contained cluster: generating
agent-readable markdown and the `llms.txt` / `llms-full.txt` feeds. These methods share
nothing with the rest of the manager beyond its public affordances, and they have
exactly three callers (`LlmsController`, `PageController`, `AiCorpusBuilder`). Moving
them into a small collaborator follows the package's own pattern (`AiCorpusBuilder`,
`SearchIndexer` — services built around the manager) and leaves the manager as the
reader-facing facade it's documented to be.

## Current state

Methods to MOVE out of `src/DocentManager.php` (line numbers at planning commit
`a95a36a`, before 001 shifts them — match by name):

- `agentMarkdown(Page $page, DocumentationContext $context): string` (318) — cached
  render of one page through `AgentMarkdownRenderer`.
- `discoveryLinkHeader(): string` (352)
- `llmsText(DocumentationContext $context): string` (360)
- `llmsFullText(DocumentationContext $context): string` (393)
- private `llmsSection(string $label, array $items): string` (520)

**Revision after first execution attempt (2026-07-18)**: the original premise that
`flattenNavigation` (504) was llms-only is FALSE — `viewerFingerprint()` also calls it
(`src/DocentManager.php:522` post-001). Resolution: `flattenNavigation` moves to
`NavigationBuilder` as a **public method named `flatten`** (same body, same PHPDoc — it
is pure navigation-structure logic and that is its natural home). The manager's
`viewerFingerprint()` then calls `$this->navigation->flatten($this->navigation($context))`
(the `$navigation` promoted property, which already coexists with the `navigation()`
method), and `AgentFeed` calls it via its own injected `NavigationBuilder`. The old
private helper is deleted from the manager.

Methods that STAY on the manager (URL/config affordances also used elsewhere):
`markdownUrl` (342), `llmsUrl` (347), `siteDescription` (419), `siteName`,
`viewerFingerprint` (533), `navigationSections` (209), `partialDocument` (576),
`sectionCards` (304), `page` (153), `config`, `route`.

Excerpt — the cluster's only external callers:

- `src/Http/Controllers/LlmsController.php:15,20` — `$docent->llmsText(...)`, `$docent->llmsFullText(...)`
- `src/Http/Controllers/PageController.php:78` — `$this->docent->agentMarkdown($page, $context)`
- `src/Http/Controllers/PageController.php:95,108` — `$this->docent->discoveryLinkHeader()`
- `src/Ai/AiCorpusBuilder.php:62` — `$this->docent->agentMarkdown($page, $context)`

(`markdownUrl` at `PageController:71` uses a method that stays — leave it.)

No Blade view or workbench file calls any moved method (verified by grep at planning
time; re-verify: `grep -rn 'agentMarkdown\|llmsText\|llmsFullText\|discoveryLinkHeader' resources/views workbench`).

`agentMarkdown` body for reference (`src/DocentManager.php:318-340`):

```php
public function agentMarkdown(Page $page, DocumentationContext $context): string
{
    $key = implode(':', [
        'agent-page',
        $this->repository->directoryHash(),
        $this->viewerFingerprint($context),
        sha1($page->slug),
    ]);

    return $this->cache->remember($key, function () use ($page, $context): string {
        $renderer = new AgentMarkdownRenderer(
            registry: $this->registry,
            context: $context,
            baseDir: $page->baseDir(),
            routePrefix: (string) $this->config('route.prefix', 'docs'),
            includeResolver: fn (string $name): ?Document => $this->partialDocument($name),
            markdownUrlResolver: fn (string $slug): string => $this->markdownUrl($slug),
            sectionCardsResolver: fn (string $section): array => $this->sectionCards($section, $context),
        );

        return $renderer->render($page->document(), $page->title(), $page->description());
    });
}
```

Repo conventions: exemplar collaborator is `src/Ai/AiCorpusBuilder.php` — final class,
promoted `private readonly` constructor, takes `DocentManager` and calls its public
affordances. Match it.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `composer test` | all pass |
| Targeted | `vendor/bin/pest tests/Feature/LlmsTest.php` (find the actual llms/agent test files with `grep -rl llms tests/`) | all pass |
| Lint | `composer lint` | exit 0 |
| Static analysis | `composer analyse` | exit 0 |

## Scope

**In scope**:

- `src/Content/AgentFeed.php` (create)
- `src/Navigation/NavigationBuilder.php` (gains public `flatten()`)
- `src/DocentManager.php` (remove moved methods + now-unused imports; `viewerFingerprint` repointed)
- `src/Http/Controllers/LlmsController.php`, `src/Http/Controllers/PageController.php`
- `src/Ai/AiCorpusBuilder.php`
- `src/Sites/SiteServices.php`, `src/DocentServiceProvider.php` (graph + ALIASES + scoped binding)
- `CHANGELOG.md`
- Tests that resolve the moved methods off the manager, if any (`grep -rn 'llmsText\|llmsFullText\|agentMarkdown\|discoveryLinkHeader' tests/`)

**Out of scope**:

- `src/Documents/Renderer/AgentMarkdownRenderer.php` — unchanged.
- `markdownUrl`, `llmsUrl`, `siteDescription` and every other manager method that stays.
- The theming/branding methods (`accent`, `logo*`, `themeStyles`, `asset*`) — explicitly
  judged NOT worth extracting; leave them on the manager.
- Pre-existing dirty files listed in plan 001's Scope — never stage or modify.

## Git workflow

- Work on `main`. One commit: `refactor: extract agent feed from DocentManager`.
- **Never add Co-Authored-By or "Generated with" lines.** Do NOT push.

## Steps

### Step 1: Create `src/Content/AgentFeed.php`

Final class `STS\Docent\Content\AgentFeed`, constructor:

```php
public function __construct(
    private readonly DocentManager $docent,
    private readonly DocumentationRepository $repository,
    private readonly DocentCache $cache,
    private readonly IntegrationRegistry $registry,
    private readonly NavigationBuilder $navigation,
) {}
```

First move `flattenNavigation` to `NavigationBuilder` as public `flatten()` (see the
revision note above) and repoint the manager's `viewerFingerprint()`; run
`composer test` to confirm green before continuing.

Then move the five remaining methods listed above, bodies unchanged except mechanical
substitutions: `$this->viewerFingerprint(...)` → `$this->docent->viewerFingerprint(...)`,
and likewise for `config`, `partialDocument`, `markdownUrl`, `sectionCards`,
`navigationSections`, `page`, `siteName`, `siteDescription`, `llmsUrl`;
`$this->flattenNavigation(...)` → `$this->navigation->flatten(...)`.
`$this->repository`, `$this->cache`, `$this->registry` stay as promoted properties.
Keep all docblocks and comments.

Delete the moved methods from `DocentManager` and prune now-unused imports
(`AgentMarkdownRenderer`, possibly `NavigationItem`/`NavigationGroup` — grep each
before removing; `NavigationItem`/`NavigationGroup` are probably still used by the
navigation delegates' PHPDoc, in which case keep them).

**Verify**: `composer analyse` → only errors are the three not-yet-updated callers.

### Step 2: Wire into the site graph

In `src/Sites/SiteServices.php` `buildAll()`, after `$manager` and before
`$corpus`:

```php
$agentFeed = new AgentFeed($manager, $repository, $cache, $registry, $navigation);
```

Add `AgentFeed::class => $agentFeed` to `$graph`, add `AgentFeed::class` to `ALIASES`,
and add the scoped binding in `src/DocentServiceProvider.php` following the existing
one-line pattern (see the `Editor` binding added by plan 001).

Change `AiCorpusBuilder`'s constructor to accept the feed (add
`private readonly AgentFeed $feed` after the existing parameters, and pass
`$agentFeed` at the construction site in `buildAll()`); change line 62 to
`$this->feed->agentMarkdown($page, $context)`.

**Verify**: `composer analyse` → remaining errors only in the two controllers.

### Step 3: Repoint the controllers

- `LlmsController`: inject `AgentFeed $feed` (method injection, matching current style)
  and call `$feed->llmsText(...)` / `$feed->llmsFullText(...)`. It still needs the
  manager for `contextFor` — keep injecting both.
- `PageController`: it constructor-injects the manager; add `AgentFeed` alongside and
  change the `agentMarkdown` and `discoveryLinkHeader` calls. `markdownUrl` stays on
  `$this->docent`.

Update any test that calls a moved method on the manager to resolve
`AgentFeed` instead (search first — there may be none).

**Verify**: `composer test` → all pass. `composer lint` → exit 0. `composer analyse` → exit 0.

### Step 4: CHANGELOG and commit

CHANGELOG entry: `agentMarkdown`, `llmsText`, `llmsFullText`, `discoveryLinkHeader`
moved from `DocentManager` to `STS\Docent\Content\AgentFeed` (resolve it from the
container; it is site-scoped like the manager). Commit in-scope files only.

**Verify**: `git show --stat HEAD` → only in-scope files.

## Test plan

Behavior-preserving move; existing llms/agent-markdown feature tests must pass
unchanged. If a test's *expected output* would need to change, STOP.

## Done criteria

- [ ] `composer test`, `composer lint`, `composer analyse` all exit 0
- [ ] `grep -n 'llmsText\|llmsFullText\|agentMarkdown\|discoveryLinkHeader\|llmsSection\|flattenNavigation' src/DocentManager.php` → no matches
- [ ] `AgentFeed::class` in `SiteServices::ALIASES` and the graph
- [ ] `git status` clean outside in-scope files; `plans/README.md` row updated (uncommitted)

## STOP conditions

- Plan 001 is not DONE.
- `flattenNavigation` has a caller beyond the llms methods and `viewerFingerprint`
  (both are now accounted for — anything else is unexpected).
- A Blade view or workbench file calls a moved method (the grep in "Current state" hits).
- Any existing test's expected values change.

## Maintenance notes

- The llms cache keys (`llms:`, `llms-full:`, `agent-page:`) are unchanged; cached
  entries survive this refactor.
- Reviewer: check `AgentFeed` made it into `ALIASES` (multi-site staleness otherwise)
  and that no method body changed beyond `$this->` → `$this->docent->` substitutions.
