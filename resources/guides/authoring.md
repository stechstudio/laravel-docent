# Writing documentation for this application

This application uses Docent (stechstudio/laravel-docent): documentation lives
as Markdown files in the repository, rendered in-app with viewer-aware
permissions. This reference covers the authoring dialect. The sections after it
list this application's sites and registered integrations. After writing or
editing pages, always validate with:

    php artisan docent:check

It catches missing titles, broken internal links, unknown integrations, missing
includes, and malformed front matter. Fix everything it reports. For a
machine-readable report to drive an automated fix loop, add `--format=json`.

You can scaffold a new page from a content-type template instead of starting
blank:

    php artisan docent:make how-to billing/refunds

The type is one of `tutorial`, `how-to`, `reference`, or `concept`; it writes a
starter page with the right front matter and section outline for that shape.

## Files and slugs

- One page per `.md` file inside the site's content directory (listed below).
- The file path is the URL slug: `billing/refunds.md` serves at
  `{prefix}/billing/refunds`. Use lowercase alphanumeric segments with hyphens.
- A slug segment must not begin with `_`. Paths like `/_search` and `/_assets`
  are reserved for Docent's internal routes; a page slug starting with `_` is
  unreachable.
- `index.md` in a directory is that directory's landing page; the root
  `index.md` is the site home.
- Directories become sidebar groups. An optional `_group.yml` in a directory
  sets `label`, `order`, `icon`, and `locked`.
- Partials live under `_partials/` and never render as pages.

## Front matter

Every page starts with a YAML block. `title` is required; the rest optional:

```yaml
---
title: Payment Methods            # page heading, tab title, search title
description: Add and remove cards. # shown under the title; feeds search + SEO
order: 2                          # sort within the group, lower first
hidden: true                      # reachable by URL, absent from navigation
authorize: billing.manage         # gate ability guarding the whole page
audience: billing-admin           # registered audience guarding the whole page
layout: landing                   # docs (default) | landing | a custom layout
redirect: billing/refunds         # serve as a redirect stub to another slug
image: /img/card.png              # social-preview image for link unfurls
locked: true                      # the web admin may not edit or override this page
search:
  exclude: true                   # keep out of the search index
  keywords:                       # up to 12 ranking hints, 80 chars each
    - insert video
---
```

Start the body at `##`; the `title` already renders as the page's `#` heading.

## Dynamic inline tokens

- `{{ value:name }}` renders a registered value from the app, optionally with
  arguments: `{{ value:usage.transactions 30d }}`.
- `{{ link:name }}` resolves a registered link; `{{ route:name }}` resolves a
  Laravel named route. Use them as Markdown link targets:
  `[billing settings]({{ route:billing.settings }})`.

Only use names that are registered in this application (inventory below) or
routes that exist; `docent:check` flags unknown ones.

## Gated and conditional blocks

Container directives open with three or more colons and close with a line of
the same colons. Attributes take `name="value"` form:

```markdown
:::can ability="billing.manage"
Only viewers passing the gate see this.
:::

:::cannot ability="billing.manage"
Shown to everyone who fails the gate.
:::

:::audience name="billing-admin"
Shown to a registered audience.
:::

:::when condition="beta-features"
Shown while a registered condition is true. `:::unless` inverts it.
:::
```

Gated content disappears everywhere at once: the page, navigation, search,
and AI answers.

## Content components

Same fence rules; nest by giving the OUTER container more colons:

```markdown
:::note title="Optional title"
Callouts: note, tip, warning, danger.
:::

::::cards
:::card title="Getting Started" icon="rocket" href="getting-started"
Card body text.
:::
::::

::::steps
:::step Install the package
Step body.
:::
::::

:::accordion How do refunds work?
Collapsible answer.
:::

::::tabs
:::tab iOS
Per-platform content.
:::
::::

:::frame caption="The billing screen"
![Alt text](/img/billing.png)
:::

:::video /demo/reconciling.mp4 caption="Reconciling in 90 seconds"
:::

:::include name="permissions-note"
:::
```

`include` pulls a partial from `_partials/<name>.md`. Card `href` values take
page slugs or external URLs. Icon names must exist in Docent's built-in set
(`docent:check` verifies them).

Embed a registered Blade component with an HTML-style element:

```markdown
<docs-component name="plan-usage" plan="team" />
```

## Code blocks

Fenced code supports a title or filename after the language:

    ```php title="Register a value"
    ```

## Rules

- Internal links use slugs relative to the docs root or the current directory;
  never hard-code the route prefix or domain.
- Keep `description` under ~160 characters; it feeds search results, SEO
  metadata, and link unfurls.
- Never invent value, link, condition, audience, or component names — use the
  inventory below, or register new ones in the application first.
- Run `php artisan docent:check` after every change and fix what it reports.
