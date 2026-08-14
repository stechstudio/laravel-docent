# Plan 003: Add an AST completeness guard — kitchen-sink fixture, renderer coverage, Tiptap round-trip

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. When done, update the status row for this plan
> in `plans/README.md` (do not commit the `plans/` directory).
>
> **Drift check (run first)**: `git diff --stat a95a36a..HEAD -- src/Documents tests/Unit`
> Source changes under `src/Documents` since `a95a36a` are fine (this plan adds tests,
> it doesn't move code) — but re-verify the file/line citations below before relying on
> them.

## Status

- **Priority**: P2
- **Effort**: M
- **Risk**: LOW (tests only — no production code changes allowed)
- **Depends on**: none (independent of 001/002)
- **Category**: tests
- **Planned at**: commit `a95a36a`, 2026-07-18

## Why this matters

The document AST (~40 node classes in `src/Documents/Ast/`) is enumerated in lockstep
by multiple transforms: `HtmlRenderer`, `AgentMarkdownRenderer`, `MarkdownExporter`,
`PlainTextRenderer`, `SearchTextRenderer`, `AstToTiptap`, and `TiptapDocumentParser`.
Several of these fall through to `default => ''` (`HtmlRenderer.php:188`,
`MarkdownExporter.php:140`), so a node type someone forgets to handle silently renders
as *nothing* — no error, no test failure, content just vanishes. Likewise the
serialize→edit→parse round-trip (`AstToTiptap` ↔ `TiptapDocumentParser`) is a contract
maintained as two independently hand-edited string tables (~30 `'type'` literals each);
a node added to one side but not the other silently drops content in the visual editor.
This plan adds tests that make both failure modes loud, without changing any production
code — the team explicitly rejected a visitor/enum rewrite as over-abstraction.

## Current state

- `src/Documents/Ast/` — all node classes. `Node.php` is the abstract base;
  `AuthorizationMode.php`, `CalloutType.php`, `AppLinkKind.php` are enums (not nodes);
  `Document` (in `src/Documents/Document.php`) is the root node.
- `src/Documents/Serializer/AstToTiptap.php:101-133` — `block()`: `match (true)` over
  `instanceof`, `default =>` wraps unknown nodes as a paragraph.
  `inlines()` (199-222) handles inline nodes, `default => null` (drops).
- `src/Documents/Parser/TiptapDocumentParser.php:96-145` — `convertBlock()`:
  `match ($type)` over the same wire strings, `default => throw` (good — already loud).
- `src/Documents/Renderer/HtmlRenderer.php:139-190` — `renderNode()` with
  `default => ''` at line 188.
- `src/Documents/Serializer/MarkdownExporter.php:118-141` — `block()` with
  `default => ''` at line 140.
- Existing test conventions: Pest, `tests/Unit/` for pure document-pipeline tests — see
  `tests/Unit/MarkdownExporterTest.php` (builds documents by parsing markdown strings
  through the parser) for style. Test helpers live inline in the test file as plain
  functions.

## Commands you will need

| Purpose | Command | Expected on success |
|---------|---------|---------------------|
| Run new tests | `vendor/bin/pest tests/Unit/AstCompletenessTest.php` | all pass |
| Full suite | `composer test` | all pass |
| Lint | `composer lint` | exit 0 |
| Static analysis | `composer analyse` | exit 0 |

## Scope

**In scope**:

- `tests/Unit/AstCompletenessTest.php` (create — the only file this plan adds)

**Out of scope** (hard rule — this plan changes NO production code):

- Everything under `src/`. If a test you write reveals a genuine mismatch or silent-drop
  bug in a renderer or the round-trip, that is a STOP condition: report the exact node
  type and transform, do not fix it yourself.
- Do not add a `TiptapNodeType` enum or restructure any `match` — explicitly rejected.
- Pre-existing dirty files (see plan 001 Scope list) — never stage or modify.

## Git workflow

- Work on `main`. One commit: `test: guard AST node coverage across renderers and the Tiptap round-trip`.
- **Never add Co-Authored-By or "Generated with" lines.** Do NOT push.

## Steps

### Step 1: Build the reflective node inventory + kitchen-sink factory

In `tests/Unit/AstCompletenessTest.php`:

1. A helper that lists every concrete (non-abstract) class in the
   `STS\Docent\Documents\Ast` namespace by scanning `src/Documents/Ast/*.php` filenames
   and filtering with reflection (`class_exists`, `is_subclass_of(Node::class)`,
   `! isAbstract()`). Exclude `Document` (the root — it wraps the fixture) and the
   enums (they won't pass the `Node` subclass filter anyway).
2. A factory: `function astFixture(string $class): ?Node` returning a minimal, valid
   instance of each node class — hand-written `match` on class name, constructing each
   node with realistic minimal arguments and (where the node is a container) one
   `Paragraph`/`Text` child via `setChildren()`. Read each node's constructor in
   `src/Documents/Ast/` to get the arguments right (e.g. `Heading` needs a level and a
   slug; `Table` must contain `TableSection` → `TableRow` → `TableCell`; `CodeBlock`
   needs code + language).
3. The guard test: every class from the inventory has a factory entry —
   `expect(astFixture($class))->not->toBeNull()`. This is what makes the suite fail
   when a future node class is added without extending these tests.

**Verify**: `vendor/bin/pest tests/Unit/AstCompletenessTest.php` → inventory test passes.

### Step 2: Renderer completeness — no silent empty output

For `HtmlRenderer` and `MarkdownExporter` (the two with silent `default => ''`): render
a `Document` containing each fixture node individually and assert the output is
non-empty — except for nodes on an explicit, commented allowlist of
legitimately-empty renderings (discover these by running; likely candidates:
`ThematicBreak` in some renderers, `SoftBreak`, `HtmlBlock` under a denying HTML
policy, gate/condition blocks whose ability doesn't pass). Every allowlist entry needs
a one-line comment saying why empty is correct.

Constructing the renderers: find how existing tests build them —
`grep -rn 'new HtmlRenderer\|HtmlRenderer(' tests/ src/DocentManager.php` and follow the
same pattern (they need an `IntegrationRegistry` and a `DocumentationContext`; existing
tests show how to make permissive ones). Use a context where gates/conditions resolve
to visible so container nodes render their children.

**Verify**: `vendor/bin/pest tests/Unit/AstCompletenessTest.php` → passes with a short,
justified allowlist. If a node unexpectedly renders empty and you cannot justify it,
STOP and report it as a probable silent-drop bug.

### Step 3: Tiptap round-trip fixpoint

For every fixture node: build `Document([$node])`, convert with
`(new AstToTiptap)->convert($document)`, `json_encode` it, parse back with
`(new TiptapDocumentParser)->parse($json)`, convert the result with `AstToTiptap`
again, and assert the two Tiptap arrays are identical (`expect($second)->toBe($first)`
on the decoded arrays). Comparing at the Tiptap level (a fixpoint check) sidesteps
AST-object identity issues while still proving nothing is dropped or re-typed.

Allowlist (with comments) for nodes that intentionally don't round-trip losslessly —
verify each by reading the code before excusing it: `HtmlInline` (serialized as plain
text, `AstToTiptap.php:208`), `SoftBreak` (becomes a space, line 206), and formatting
wrappers (`Emphasis`, `Strong`, `Strikethrough`, `Link`, `InlineCode`) which flatten to
marks — for these, assert the round-trip preserves the *text content* instead.

**Verify**: `vendor/bin/pest tests/Unit/AstCompletenessTest.php` → all pass.
`composer test` → full suite passes. `composer lint` && `composer analyse` → exit 0.

### Step 4: Commit

Commit the single new test file.

**Verify**: `git show --stat HEAD` → exactly one file added.

## Test plan

This plan IS tests. Coverage added: node-inventory guard (fails on unregistered new
node classes), non-empty-render guard for the two silent-default transforms, and a
serializer/parser round-trip fixpoint for every node type.

## Done criteria

- [ ] `tests/Unit/AstCompletenessTest.php` exists and covers every concrete `Node`
      subclass in `src/Documents/Ast/` via reflection (adding a new node class with no
      factory entry makes the suite fail)
- [ ] `composer test` exits 0 (no production code changed: `git diff --stat HEAD~1 -- src/` empty)
- [ ] `composer lint` and `composer analyse` exit 0
- [ ] Every allowlist entry has a justification comment
- [ ] `plans/README.md` row updated (uncommitted)

## STOP conditions

- A test reveals a real silent-drop or round-trip mismatch (a node renders empty with
  no justification, or the fixpoint differs). Report the node type, transform, and the
  two outputs — do not patch `src/`.
- You cannot construct a valid instance of some node class from its constructor
  signature after reading its source and its usages in `src/Documents/Parser/`.
- The allowlist grows beyond ~8 entries — that suggests the approach needs rethinking,
  not more exclusions.

## Maintenance notes

- When someone adds an AST node type, this suite fails until the factory gains an entry
  — that's the point. The new entry forces them to check every transform handles it.
- Reviewer: read the allowlists skeptically; each entry is a claim that empty/lossy is
  intentional.
