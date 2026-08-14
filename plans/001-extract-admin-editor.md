# Plan 001: Extract DocentManager's admin/editor cluster into `STS\Docent\Admin\Editor`

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/README.md` (do not commit the `plans/` directory).
>
> **Drift check (run first)**: `git diff --stat a95a36a..HEAD -- src/DocentManager.php src/Http/Controllers/Admin src/Sites/SiteServices.php src/DocentServiceProvider.php`
> If any in-scope file changed since this plan was written, compare the
> "Current state" excerpts against the live code before proceeding; on a
> mismatch, treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: M
- **Risk**: MED
- **Depends on**: none
- **Category**: tech-debt
- **Planned at**: commit `a95a36a`, 2026-07-18

## Why this matters

`src/DocentManager.php` is 1,459 lines. Lines 834–1458 — nearly half the file — are the
admin panel's authoring backend (page tree, editor payloads, drafts, previews, markdown
export, group metadata, picker metadata). Every method in that block is called **only**
by the controllers in `src/Http/Controllers/Admin/`, and it is the only part of the
manager that touches Eloquent models (`DocentPage`, `DocentPageRevision`). Extracting it
gives the reader-facing manager and the admin editor independent, cohesive homes, and
cuts the file the whole package revolves around roughly in half. No behavior changes.

## Current state

- `src/DocentManager.php` — the facade root (`Docent` facade resolves to it). The admin
  cluster to move is lines 834–1458.
- `src/Http/Controllers/Admin/` — the only callers of the cluster: `TreeController`,
  `GroupController`, `MetaController`, `PageController`, `PreviewController`,
  `ExportController`, `PageStateController`, and the trait
  `Concerns/InteractsWithPages.php`.
- `src/Sites/SiteServices.php` — builds one service graph per site (`buildAll()`, line
  109) and lists the per-site scoped aliases in `SiteServices::ALIASES` (line 46).
- `src/DocentServiceProvider.php` — registers one scoped container binding per aliased
  class (lines 98–103).
- `src/Documents/Document.php` — a 26-line final class (AST root + front matter).
- `tests/Feature/CheckCommandTest.php:184` — the single test call site of a cluster
  method: `app(DocentManager::class)->draftIssues('components', $document)`.

### Methods that MOVE from DocentManager to the new Editor class

Public API (currently at these lines): `adminTree` (834), `adminDetail` (897),
`filesystemSlugLocked` (916), `adminGroups` (947), `updateGroupMeta` (1003),
`removeGroupMeta` (1019), `overrideFromFilesystem` (1031), `draftDocument` (1064),
`tiptapError` (1079), `exportMarkdown` (1096), `previewDraft` (1129), `draftIssues`
(1149), `pickerMeta` (1186).

Private helpers used only by the cluster (move with it): `abilityLabel` (1203),
`databaseDetail` (1222), `filesystemDetail` (1248), `tiptapFor` (1287),
`splitFrontMatter` (1306), `composeMarkdown` (1323), `tocToArray` (1336), `baseDirOf`
(1349), `collectDirectories` (1361), `isUnderscored` (1375), `databaseConnection`
(1386).

### Methods that STAY on DocentManager (shared with the reader path)

- `withFrontMatter` (1430) and `withHtmlPolicy` (1438) are pure "copy of the document
  with X replaced" helpers used by both the reader's `document()` (1404) and the
  cluster. They become instance methods **on `Document` itself** (step 1), then the
  private manager helpers are deleted.
- `databaseHtmlPolicy` (1453) is used by the reader's `sourceHtmlPolicy` (1446) and by
  `previewDraft`. It stays on the manager but becomes **public** so Editor can call it.
- `validRedirectSlug` (1266), `document` (1404), `sourceHtmlPolicy` (1446) — reader
  path, untouched.

Excerpt of the two helpers moving onto `Document` (`src/DocentManager.php:1430-1444`):

```php
private function withFrontMatter(Document $document, array $frontMatter): Document
{
    $replacement = new Document(new FrontMatter($frontMatter), $document->line, $document->htmlPolicy);
    $replacement->setChildren($document->children);

    return $replacement;
}

private function withHtmlPolicy(Document $document, HtmlPolicy $policy): Document
{
    $replacement = new Document($document->frontMatter, $document->line, $policy);
    $replacement->setChildren($document->children);

    return $replacement;
}
```

### Repo conventions to match

- PHP 8.3, `declare(strict_types=1)`, `final` classes, constructor property promotion
  with `private readonly`, PHPDoc `@return` shapes for array-returning methods. Exemplar
  for a manager-collaborator service: `src/Ai/AiCorpusBuilder.php` (takes
  `DocentManager` in its constructor and uses its affordances).
- Keep every moved method's existing docblock. Do not rewrite comments.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Tests | `composer test` | all pass (552 tests at planning time) |
| Targeted tests | `vendor/bin/pest tests/Admin` | all pass |
| Lint | `composer lint` | exit 0 |
| Static analysis | `composer analyse` | exit 0, no errors |

## Scope

**In scope** (the only files you should modify or create):

- `src/Admin/Editor.php` (create)
- `src/DocentManager.php`
- `src/Documents/Document.php`
- `src/Http/Controllers/Admin/*.php` and `src/Http/Controllers/Admin/Concerns/InteractsWithPages.php`
- `src/Sites/SiteServices.php`
- `src/DocentServiceProvider.php`
- `tests/Feature/CheckCommandTest.php` (one line)
- `CHANGELOG.md` (note the moved API)

**Out of scope** (do NOT touch, even though they look related):

- `src/Http/Controllers/Admin/UploadController.php` and `SearchQueryController.php` —
  they don't call the cluster; leave them alone unless `grep` proves otherwise.
- The reader-path methods of `DocentManager` (everything before line 834 except the
  helper changes named above).
- `resources/js/docent-admin.js` and all Blade views — the JSON payload shapes must not
  change at all.
- Pre-existing dirty files in the working tree (`resources/css/docent.css`,
  `resources/dist/*`, `resources/js/docent-assistant.js`,
  `tests/Browser/assistant.spec.js`, `.design-logo-board.html`,
  `workbench/resources/docs/**/old-*.md`) — never stage, commit, or modify them.

## Git workflow

- Work on `main` directly (matches how this package's task commits have been made).
- One commit for this whole plan. Message style: `refactor: extract admin editor backend from DocentManager` (see `git log --oneline -10` for tone).
- **Never add Co-Authored-By or "Generated with" lines to commit messages.**
- Do NOT push.

## Steps

### Step 1: Give `Document` the copy affordances

In `src/Documents/Document.php`, add two public methods (keep the class final):

```php
/**
 * A copy of this document with its front matter replaced — the seam that lets
 * a Tiptap source's out-of-band metadata override the empty front matter the
 * JSON parser produces.
 *
 * @param  array<string, mixed>  $frontMatter
 */
public function withFrontMatter(array $frontMatter): self
{
    $replacement = new self(new FrontMatter($frontMatter), $this->line, $this->htmlPolicy);
    $replacement->setChildren($this->children);

    return $replacement;
}

/** A copy of this document rendered under a different HTML policy. */
public function withHtmlPolicy(HtmlPolicy $policy): self
{
    $replacement = new self($this->frontMatter, $this->line, $policy);
    $replacement->setChildren($this->children);

    return $replacement;
}
```

In `src/DocentManager.php`, replace every `$this->withFrontMatter($doc, $fm)` with
`$doc->withFrontMatter($fm)` and every `$this->withHtmlPolicy($doc, $policy)` with
`$doc->withHtmlPolicy($policy)` (call sites: lines 1067, 1131, 1418, 1420), then delete
the two private helpers. Make `databaseHtmlPolicy()` public (docblock: it's the policy
database-authored content renders under).

**Verify**: `composer test` → all pass. `composer analyse` → exit 0.

### Step 2: Create `src/Admin/Editor.php`

New final class `STS\Docent\Admin\Editor`. Constructor:

```php
public function __construct(
    private readonly DocentManager $docent,
    private readonly DocumentationRepository $repository,
    private readonly FilesystemRepository $filesystem,
    private readonly DocumentParser $parser,
    private readonly IntegrationRegistry $registry,
) {}
```

Move the 13 public methods and 11 private helpers listed in "Current state" into it,
bodies unchanged except these mechanical substitutions:

- `$this->key()` → `$this->docent->key()`
- `$this->config(...)` → `$this->docent->config(...)`
- `$this->renderDocument(...)` → `$this->docent->renderDocument(...)`
- `$this->databaseHtmlPolicy()` → `$this->docent->databaseHtmlPolicy()` (now public)
- `$this->withFrontMatter($doc, $fm)` → `$doc->withFrontMatter($fm)` (from step 1)
- In `draftIssues`, `docent: $this` → `docent: $this->docent`
- `$this->repository`, `$this->filesystem`, `$this->parser`, `$this->registry` resolve
  to the promoted properties — same names, no change needed.

Give the class a short docblock: the admin panel's authoring backend — tree, editor
payloads, drafts, previews, export, and group metadata for one site. Then delete the
moved methods from `DocentManager` and remove imports that are now unused there
(`DocentPage`, `DocentPageRevision`, `AstToTiptap`, `MarkdownExporter`, `CheckContext`,
`DocsChecker`, `Issue`, `Icon`, `Yaml`, `Gate`, `JsonException`,
`InvalidArgumentException`, `TableOfContents`, `TocEntry` — verify each with grep before
removing; keep any that are still referenced).

**Verify**: `composer analyse` → the only remaining errors should be the not-yet-updated
callers (fix in steps 3–4). `wc -l src/DocentManager.php` → roughly 830 lines.

### Step 3: Wire the Editor into the per-site graph

1. `src/Sites/SiteServices.php` — in `buildAll()` (after `$manager` is constructed,
   line ~167), add:
   ```php
   $editor = new Editor($manager, $repository, $filesystem, $this->app->make(DocumentParser::class), $registry);
   ```
   Add `Editor::class => $editor` to the `$graph` array, and `Editor::class` to the
   `ALIASES` constant (line 46).
2. `src/DocentServiceProvider.php` — add one scoped binding following the exact pattern
   of lines 99–103:
   ```php
   $this->app->scoped(Editor::class, static fn (Application $app): object => $app->make(SiteRegistry::class)->service(Editor::class));
   ```

This makes `Editor` injectable in admin controllers, always resolved for the current
site, and correctly forgotten when the selected site changes mid-request
(`CurrentSite::set()` iterates `SiteServices::ALIASES`).

**Verify**: `vendor/bin/pest tests/Feature/Sites` → all pass.

### Step 4: Repoint the admin controllers and the trait

For each of `TreeController`, `GroupController`, `MetaController`, `PageController`,
`PreviewController`, `ExportController`, `PageStateController` in
`src/Http/Controllers/Admin/`: change the injected `DocentManager $docent` parameters to
`Editor $editor` **where the call is to a moved method**. A controller that also uses
non-moved manager methods (`key()`, `config()`, `contextFor()`) injects both. Method
injection is the existing style — keep it.

In `Concerns/InteractsWithPages.php`:

- `assertUnlocked(string $slug, DocentManager $docent)` → `assertUnlocked(string $slug, Editor $editor)`
  calling `$editor->filesystemSlugLocked($slug)`; update all callers.
- The trait's private `docent()` helper (`app(DocentManager::class)`) and `connection()`
  stay as they are — `pageQuery()` legitimately uses the manager's site key and config.

In `tests/Feature/CheckCommandTest.php:184`, change
`app(DocentManager::class)->draftIssues(...)` to
`app(\STS\Docent\Admin\Editor::class)->draftIssues(...)`.

**Verify**: `composer test` → all pass. `composer analyse` → exit 0. `composer lint` → exit 0.

### Step 5: CHANGELOG and commit

Add a short entry under the unreleased notes in `CHANGELOG.md`: the admin authoring API
moved from `DocentManager` to `STS\Docent\Admin\Editor` (list the 13 method names);
apps that called these on the manager or `Docent` facade must resolve the site's
`Editor` instead. Commit (excluding `plans/` and the pre-existing dirty files listed in
Scope).

**Verify**: `git show --stat HEAD` → only in-scope files.

## Test plan

No new tests: this is a behavior-preserving move and the admin surface is already
covered by `tests/Admin/` (HTTP-level) plus `tests/Feature/CheckCommandTest.php`. The
full suite passing unchanged (except the one resolution line) IS the verification. If
any test needs its expectations changed beyond that one line, that's a STOP condition —
the move broke behavior.

## Done criteria

- [ ] `composer test` exits 0; same test count as before the change (552 at planning time)
- [ ] `composer lint` and `composer analyse` exit 0
- [ ] `grep -n 'adminTree\|adminDetail\|adminGroups\|previewDraft\|pickerMeta\|overrideFromFilesystem' src/DocentManager.php` → no matches
- [ ] `src/Admin/Editor.php` exists; `wc -l src/DocentManager.php` ≤ 850
- [ ] `Editor::class` appears in `SiteServices::ALIASES` and in the `buildAll()` graph
- [ ] `git status` shows no modifications outside the in-scope list
- [ ] `plans/README.md` status row updated (not committed)

## STOP conditions

Stop and report back (do not improvise) if:

- The drift check shows in-scope files changed and the excerpts no longer match.
- Any existing test needs its *expected values* changed (not just how it resolves the
  service) — the extraction must be behavior-preserving.
- You find a caller of a moved method outside `src/Http/Controllers/Admin/` and
  `tests/Feature/CheckCommandTest.php` that this plan doesn't account for.
- PHPStan reports a circular dependency or container resolution error you cannot fix by
  following step 3's pattern exactly.

## Maintenance notes

- Future admin features (new panel endpoints) belong on `Editor`, not the manager.
- Reviewer should scrutinize: that no moved method body changed beyond the listed
  mechanical substitutions (diff the moved block against the old file), and that
  `Editor` was added to `ALIASES` (forgetting it causes stale-site bugs on multi-site
  installs when the selected site changes mid-scope).
- Deferred: plan 004 replaces the `'database'`/`'filesystem'`/`'file'` provenance string
  literals inside `Editor` with class constants — don't do it here.
