# Plan 007: Neutralize `javascript:`/`data:` hrefs in markdown links, cards, and videos

> **Executor instructions**: Follow this plan step by step. Run every
> verification command and confirm the expected result before moving to the
> next step. If anything in the "STOP conditions" section occurs, stop and
> report — do not improvise. Do NOT update `plans/README.md` or commit — a
> reviewer is maintaining the index and will handle git.
>
> **Drift check (run first)**:
> `git diff --stat 061a4c0..HEAD -- src/Documents/Renderer/HtmlRenderer.php src/Support/InternalLink.php`
> If either file changed since this plan was written, compare the "Current
> state" excerpts against the live code before proceeding; on a mismatch,
> treat it as a STOP condition.

## Status

- **Priority**: P1
- **Effort**: S
- **Risk**: LOW
- **Depends on**: none (do this FIRST — it is a security fix and blocks the v1 tag)
- **Category**: security
- **Planned at**: commit `061a4c0`, 2026-07-20

## Why this matters

A documentation author can currently inject a stored XSS payload through an
ordinary markdown link or a card/video href. `[click me](javascript:alert(document.cookie))`
renders as a live `<a href="javascript:alert(document.cookie)">` — the renderer
escapes the *quotes* but not the URL *scheme*, so the script survives and runs
in the clicking reader's session (including an admin's). The exploitable path is
the **database page editor**: a lower-trust content editor holding only the
`viewDocentAdmin` gate can plant a payload that later runs in a full admin's
browser. This was confirmed with a live render at commit `061a4c0`: the
`javascript:` href appeared verbatim in the output HTML for both a link and a
card, while `https:`, `mailto:`, and internal links resolved correctly.

Every *other* renderer in the package already blocks this (`AiAnswerRenderer`
sets `allow_unsafe_links => false`; `ContentHtmlSanitizer` strips it for raw
HTML), which is what makes this a genuine oversight rather than a design choice.
The sanitizer only runs on raw `HtmlBlock`/`HtmlInline` nodes — it never sees a
markdown `Link`, `Card`, or `Video` AST node, so those three sinks are
unprotected regardless of the `content.database.sanitize_html` setting.

## Current state

Three anchor sinks in `src/Documents/Renderer/HtmlRenderer.php` emit an
author-controlled href through `e()` only:

- `renderLink()` — `src/Documents/Renderer/HtmlRenderer.php:473`:
  ```php
  return '<a href="'.e($href).'"'.$title.'>'.$this->renderChildren($node).'</a>';
  ```
  `$href` comes from `resolveUrl($node->url)` (line ~460, the value returned by
  the private `resolveUrl()` at line 525).

- `renderCard()` — `src/Documents/Renderer/HtmlRenderer.php:265-266`:
  ```php
  if ($href !== null) {
      return '<a class="docent-card" href="'.e($href).'">'.$inner.'</a>';
  }
  ```
  `$href` here is also produced by `resolveUrl(...)` earlier in the method.

- `renderVideo()` unsupported-fallback — `src/Documents/Renderer/HtmlRenderer.php:375`:
  ```php
  return '<figure class="docent-video docent-video-unsupported"><a href="'.e($node->url).'">'
      .e($label).'</a>'.$caption.'</figure>';
  ```
  Here the raw `$node->url` is used directly (NOT through `resolveUrl`).

The shared resolver, `src/Documents/Renderer/HtmlRenderer.php:525-537`:
```php
$target = InternalLink::resolve(
    $destination,
    (string) ($this->options['base_dir'] ?? ''),
    (string) ($this->options['route_prefix'] ?? 'docs'),
);

if ($this->urlResolver === null || $target === null) {
    return $destination;   // <-- external/unresolved destinations pass through verbatim
}

return (($this->urlResolver)($target['slug']) ?? $destination).$target['suffix'];
```

`src/Support/InternalLink.php:25-29` — anything with a scheme is treated as
"external" and returns `null`, which is why it reaches the `return $destination`
line above unfiltered:
```php
public static function resolve(string $destination, string $baseDir, string $routePrefix): ?array
{
    if ($destination === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $destination) === 1) {
        return null;
    }
```

**Convention to match**: this repo favors a small `final` class in
`src/Support/` for a single shared responsibility (see `InternalLink` itself).
The fix adds a sibling `SafeUrl` there. Renderer methods are private, terse, and
string-concatenating; match that style (no new abstractions beyond the one
helper). Tests are Pest, in `tests/Unit/`, using the `renderHtml(...)` helper —
see `tests/Unit/HtmlRendererTest.php:11-20`.

## Commands you will need

| Purpose   | Command                                   | Expected on success |
|-----------|-------------------------------------------|---------------------|
| Tests     | `composer test`                           | all pass, exit 0    |
| One file  | `vendor/bin/pest tests/Unit/HtmlRendererTest.php` | all pass    |
| Lint      | `composer lint`                           | exit 0              |
| Analyse   | `composer analyse`                        | exit 0, no errors   |

## Scope

**In scope** (the only files you should modify or create):
- `src/Support/SafeUrl.php` (create)
- `src/Documents/Renderer/HtmlRenderer.php` (modify the three sinks)
- `tests/Unit/SafeUrlTest.php` (create)
- `tests/Unit/HtmlRendererTest.php` (add regression cases)

**Out of scope** (do NOT touch):
- `src/Support/InternalLink.php` — its scheme detection is correct for its job
  (deciding internal vs external); do not change its return contract.
- `src/Content/ContentHtmlSanitizer.php` and the raw-HTML pipeline — the raw-HTML
  path is already safe; this plan is only about AST link/card/video nodes.
- `src/Documents/Renderer/AgentMarkdownRenderer.php`, `MarkdownExporter.php`,
  `PlainTextRenderer.php`, `SearchTextRenderer.php` — the markdown/text feeds
  emit `[label](url)` as text, not clickable HTML; no XSS sink there. Do not
  modify them.
- `src/Console` / `docent:check` — a validation warning for unsafe schemes would
  be a nice-to-have but is explicitly deferred (see Maintenance notes).

## Git workflow

- Do NOT commit, branch, or push. Leave all changes in the working tree; the
  reviewer inspects `git diff` and handles commits.
- If you normally auto-commit, suppress it for this task.

## Steps

### Step 1: Add the `SafeUrl` helper

Create `src/Support/SafeUrl.php`:

```php
<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * Neutralizes dangerous URL schemes on author-supplied link destinations before
 * they reach an HTML `href`. Relative paths, `#` anchors, and query-only
 * destinations are always safe; absolute URLs are allowed only for an explicit
 * scheme allowlist. Anything else (notably `javascript:` and `data:`) is
 * rejected, so a markdown link or card/video href cannot smuggle script.
 */
final class SafeUrl
{
    /** Schemes permitted on an absolute href. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel', 'ftp'];

    /**
     * Return the destination unchanged when it is safe to place in an `href`,
     * or null when its scheme is not allowlisted. A null result means "do not
     * emit a link" — render the label as plain text instead.
     */
    public static function filter(string $destination): ?string
    {
        $trimmed = ltrim($destination);

        // Relative path, root-relative path, pure anchor, or query — no scheme.
        if ($trimmed === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $trimmed) !== 1) {
            return $destination;
        }

        // Protocol-relative `//host` has no scheme token but is an absolute URL
        // to http(s); allow it (the regex above already let it fall through only
        // when it starts with `//`, handled here for clarity).
        if (str_starts_with($trimmed, '//')) {
            return $destination;
        }

        $scheme = strtolower((string) strstr($trimmed, ':', true));

        return in_array($scheme, self::ALLOWED_SCHEMES, true) ? $destination : null;
    }
}
```

Note the regex: `^(?:[a-z][a-z0-9+.-]*:|\/\/)` matches EITHER a scheme token
`word:` OR a leading `//`. If neither matches, the destination is relative/anchor
and returned as-is. When a `word:` scheme matches, it is checked against the
allowlist. `//`-prefixed protocol-relative URLs are matched by the `\/\/` branch
and returned as-is before the `strstr` scheme parse.

**Verify**: `php -r "require 'vendor/autoload.php'; use STS\Docent\Support\SafeUrl; var_dump(SafeUrl::filter('javascript:alert(1)'), SafeUrl::filter('https://x.com'), SafeUrl::filter('mailto:a@b.com'), SafeUrl::filter('getting-started/intro'), SafeUrl::filter('#anchor'), SafeUrl::filter('//cdn.x.com/a'), SafeUrl::filter('data:text/html,<script>'), SafeUrl::filter('  javascript:alert(1)'));"`
→ Expected: `NULL`, `string "https://x.com"`, `string "mailto:a@b.com"`, `string "getting-started/intro"`, `string "#anchor"`, `string "//cdn.x.com/a"`, `NULL`, `NULL` (the last one confirms leading-whitespace evasion is blocked).

### Step 2: Filter the markdown link sink

In `renderLink()` (`src/Documents/Renderer/HtmlRenderer.php:460-474`), `$href` is
computed by a ternary (an `AppLink` resolves via `resolveAppLink()`, everything
else via `resolveUrl()`), then a null-check renders the label unlinked. The
EXISTING code is:
```php
private function renderLink(Link $node): string
{
    $href = $node->destination instanceof AppLink
        ? $this->resolveAppLink($node->destination)
        : $this->resolveUrl($node->destination);

    if ($href === null) {
        // Unresolved app link: still render the label, unlinked.
        return $this->renderChildren($node);
    }
    // ... builds <a href="'.e($href).'"> ...
```
Do NOT try to find/replace `$this->resolveUrl($node->url)` — that string does
not exist (a `Link` node uses `$node->destination`). Instead, fold the filter
into the existing `if ($href === null)` check so BOTH branches
(`resolveAppLink` and `resolveUrl` output) are covered. Change the null-check to:
```php
    if ($href === null || ($href = SafeUrl::filter($href)) === null) {
        // Unresolved app link OR unsafe scheme: render the label, unlinked.
        return $this->renderChildren($node);
    }
```
Leave the ternary that computes `$href` exactly as it is. Add
`use STS\Docent\Support\SafeUrl;` to the file's imports (alongside the existing
`use STS\Docent\Support\InternalLink;`). (Running `resolveAppLink()` output — an
internal app URL — through `SafeUrl::filter()` is harmless; it passes http/https.)

**Verify**: `vendor/bin/pest tests/Unit/HtmlRendererTest.php` → all pass (no regression yet; new tests come in Step 5).

### Step 3: Filter the card href sink

In `renderCard()` (`src/Documents/Renderer/HtmlRenderer.php:~265`), the `$href`
is already resolved earlier in the method. Wrap the anchor branch so an unsafe
scheme downgrades the card to its non-linked `<div>` form (which the method
already returns at line 269 when `$href === null`):

```php
if ($href !== null && ($safe = SafeUrl::filter($href)) !== null) {
    return '<a class="docent-card" href="'.e($safe).'">'.$inner.'</a>';
}

return '<div class="docent-card">'.$inner.'</div>';
```

**Verify**: `vendor/bin/pest tests/Unit/CardsTest.php tests/Unit/SectionCardsTest.php` → all pass.

### Step 4: Filter the video-fallback href sink

In `renderVideo()` (`src/Documents/Renderer/HtmlRenderer.php:372-377`), the
unsupported-source fallback links the raw `$node->url`. Filter it; when unsafe,
emit the label as plain text instead of an anchor:

```php
if ($source === null) {
    $label = $node->caption !== null && $node->caption !== '' ? $node->caption : 'Video';
    $safe = SafeUrl::filter($node->url);
    $link = $safe !== null ? '<a href="'.e($safe).'">'.e($label).'</a>' : e($label);

    return '<figure class="docent-video docent-video-unsupported">'.$link.$caption.'</figure>';
}
```

**Verify**: `composer test` → all pass.

### Step 5: Add regression tests

Add to `tests/Unit/HtmlRendererTest.php` (use the existing `renderHtml(...)`
helper at line 11; model structure after the tests already in that file):

```php
it('strips javascript: scheme from markdown links but keeps the label', function () {
    $html = renderHtml('[click me](javascript:alert(document.cookie))', docRegistry(), docContext());

    expect($html)->toContain('click me')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('<a ');
});

it('allows safe schemes and relative links through', function () {
    expect(renderHtml('[x](https://example.com)', docRegistry(), docContext()))->toContain('href="https://example.com"')
        ->and(renderHtml('[x](mailto:a@b.com)', docRegistry(), docContext()))->toContain('href="mailto:a@b.com"');
});

it('downgrades a card with a javascript: href to a non-linked card', function () {
    $md = "::::cards\n:::card title=\"Bad\" href=\"javascript:alert(1)\"\nbody\n:::\n::::";
    $html = renderHtml($md, docRegistry(), docContext());

    expect($html)->toContain('docent-card')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('<a class="docent-card"');
});
```

Create `tests/Unit/SafeUrlTest.php` covering `SafeUrl::filter()` directly:
happy schemes (`http`, `https`, `mailto`, `tel`), relative/anchor/query
passthrough, protocol-relative passthrough, and rejection of `javascript:`,
`data:`, `vbscript:`, uppercase `JavaScript:`, and leading-whitespace
`  javascript:` (returns null for all rejections). Model the file structure
after `tests/Unit/InternalLinkTest.php`.

**Verify**: `composer test` → all pass, including the new `SafeUrlTest` and the
three new `HtmlRendererTest` cases.

## Test plan

- `tests/Unit/SafeUrlTest.php` (new): unit-tests the allowlist — allowed schemes
  pass, relative/anchor/protocol-relative pass, dangerous schemes (`javascript`,
  `data`, `vbscript`), case-insensitivity, and whitespace-prefixed evasion all
  return null.
- `tests/Unit/HtmlRendererTest.php` (add 3 cases): the actual regression — a
  `javascript:` markdown link and card href produce no `href` and no script,
  while safe links still render.
- Structural patterns: `tests/Unit/InternalLinkTest.php` for the unit test,
  the existing cases in `tests/Unit/HtmlRendererTest.php` for the renderer tests.
- Verification: `composer test` → all pass; the suite gains one new test file
  and three new renderer cases.

## Done criteria

Machine-checkable. ALL must hold:

- [ ] `composer test` exits 0; `tests/Unit/SafeUrlTest.php` exists and passes;
      the three new `HtmlRendererTest` cases pass.
- [ ] `composer lint` exits 0.
- [ ] `composer analyse` exits 0, no errors.
- [ ] `grep -n "SafeUrl::filter" src/Documents/Renderer/HtmlRenderer.php` returns
      three matches (link, card, video sinks).
- [ ] This one-liner prints nothing (no unsafe href survives):
      `php -r "require 'vendor/autoload.php'; use STS\Docent\Support\SafeUrl; echo SafeUrl::filter('javascript:alert(1)') ?? '';"`
- [ ] No files outside the in-scope list are modified (`git status`).

## STOP conditions

Stop and report back (do not improvise) if:

- The "Current state" excerpts don't match the live code (drift since `061a4c0`).
- A verification command fails twice after a reasonable fix attempt.
- You find a fourth author-controlled href sink not listed here (e.g. a new
  component renderer). Report it rather than guessing whether it needs filtering.
- Any existing test's expected value would have to change to make the suite pass
  — that means the fix altered legitimate output; report instead of editing the
  assertion.

## Maintenance notes

- For the reviewer: confirm the allowlist is scheme-based (not a `javascript:`
  denylist — denylists miss `vbscript:`, `data:`, and future vectors). Confirm
  `mailto:`/`tel:` still work, since docs legitimately use them.
- Deferred out of scope: adding an unsafe-scheme warning to `docent:check` so
  authors learn at build time that a link was neutralized. Worth a follow-up
  plan; not required for the security fix.
- If a future feature adds another place that emits an author-supplied `href`
  (a new directive, an admin-configurable CTA), it must route through
  `SafeUrl::filter()`. Grep for `href="'.e(` in the renderer to audit sinks.
