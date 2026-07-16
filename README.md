# Docent

**Give your Laravel app a guide.**

Docent installs a beautiful, fast, searchable documentation site inside your Laravel application. And unlike a static docs generator, the documentation *participates* in your app: pages can respond to who the viewer is, what they're authorized to see, which features are enabled, and what's actually happening in their account.

```bash
composer require stechstudio/laravel-docent
php artisan docent:install
```

Visit `/docs`. Done.

## Why in-app documentation?

A hosted docs platform renders your Markdown. Docent renders your Markdown *inside your application's runtime*, which means documentation can:

- Require your app's authentication, using your guards and middleware
- Hide entire pages behind gates and policies, from the page, the navigation, **and search**
- Show admins different instructions than members, in the same document
- Render live values from the viewer's account ("You've used 7,450 of 10,000 transactions")
- Link to named routes that survive refactors
- Embed real Blade-rendered components from your app
- Deploy atomically with the features it describes, validated in CI

## Writing documentation

Docs are Markdown files with YAML front matter, living in `resources/docs`:

```markdown
---
title: Payment Methods
description: Add, update, and remove payment methods.
authorize: billing.view        # gate the whole page
order: 2
---

You can store multiple payment methods and designate one as the default.

:::can ability="billing.manage"
Head to [billing settings]({{ link:billing.settings }}) to add a card.
:::

:::cannot ability="billing.manage"
Only an account administrator can change the payment method.
:::

Your account is on the **{{ value:account.plan }}** plan.

<docs-component name="plan-usage" />
```

Directories become navigation groups (customize with a `_group.yml`), `_partials/` holds reusable fragments for `:::include`, and callouts use the same fence syntax: `:::note`, `:::tip`, `:::info`, `:::warning`, `:::danger`.

Task-focused guides can also use `:::steps` with titled `:::step` items,
collapsible `:::accordion` answers, `:::tabs` with labeled `:::tab` panels,
`:::frame caption="…"` around screenshots, click-to-load `:::video` embeds, and
`::::code-group` blocks for related examples. These remain readable in search,
print, and agent-facing Markdown; headings inside accordions and tabs keep their
normal deep links and table-of-contents entries.

When the database-backed admin is enabled, editors can normally create an editable copy of a repository page. Add `locked: true` to a page's front matter when the repository version must always win. The same key in `_group.yml` locks every repository page and partial below that directory; a page-level `locked: false` cannot weaken the group lock. Locked pages remain visible in the admin as rendered, read-only content.

Admin image uploads support PNG, JPEG, GIF, WebP, and SVG on any configured
Laravel filesystem disk. Docent streams them through the documentation route
and uses private immutable browser caching by default. SVGs are sanitized at
upload — scripts, event handlers, and embedded foreign content never reach the
disk — and are additionally served with a restrictive document policy so they
remain safe when their raw URL is opened.
Only enable shared caching when the documentation and every uploaded image are
intentionally public:

```php
'admin' => [
    'uploads' => [
        'public_cache' => true,
    ],
],
```

Repository Markdown and browser-authored pages have separate HTML trust
policies. Raw HTML in repository files remains enabled by default because those
files are reviewed application code. Raw HTML entered through the database
admin—including previews, published pages, and database partials—is sanitized
by default: ordinary structural HTML, classes, links, and media remain useful,
while scripts, event handlers, unsafe URLs, embedded frames, and inline styles
are removed. The stored source remains unchanged. Applications whose publishing
editors are trusted to deploy code may explicitly disable sanitization:

```php
'content' => [
    'database' => [
        'sanitize_html' => false,
    ],
],
```

## Connecting your application

Documentation never contains raw PHP or Blade. Instead, your app registers stable, allowlisted integrations, typically in a service provider:

```php
use STS\Docent\Facades\Docent;

Docent::value('account.plan', fn ($context) => $context->user?->account->plan->name ?? 'Free');

Docent::link('billing.settings', fn () => route('billing.settings'));

Docent::condition('advanced-exports', fn ($context) => $context->user?->account->allowsAdvancedExports() ?? false);

Docent::component('plan-usage', PlanUsageComponent::class);

Docent::audience('billing-admin', fn ($context) => $context->user?->can('billing.manage') ?? false);
```

Your internals can refactor freely; the identifiers your docs reference stay stable, and `docent:check` catches any drift.

## Permission-safe by design

Authorization isn't a rendering detail. It's enforced at every surface:

- Pages: front matter `authorize` / `audience` gates the page (404 by default, configurable)
- Navigation: unauthorized pages simply don't appear
- Search: server-side, filtered through the same authorization before results are returned; conditional block content is never indexed, so a snippet can never leak gated text
- Table of contents: headings inside conditional blocks only appear for viewers who'd see them

## Optional grounded answers

Docent can add an **Assistant** that answers from the help the current viewer
can read. Readers can hand a search query to it, open it from the top bar, or
press `Cmd/Ctrl+I`. Answers stream into a full-height panel with formatted
code, copy controls, feedback, and links limited to pages Docent supplied.
Readers can ask follow-up questions, follow a source without closing the panel,
and return to the same temporary conversation in the current browser tab.

The feature is off by default and uses your own Prism provider and key:

```bash
composer require prism-php/prism
```

```php
'ai' => [
    'enabled' => true,
    'provider' => env('DOCENT_AI_PROVIDER'),
    'model' => env('DOCENT_AI_MODEL'),
],
```

Publish and run Docent's migrations to log questions and thumbs feedback. Set
`log_questions` to `false` when no question analytics should be stored. The
question log never stores answer content or conversation transcripts.

Conversation memory is intentionally short-lived. Docent keeps complete turns
in your configured Laravel cache, binds them to the current viewer and docs
surface with a signed token, and drops the oldest pairs as the configured turn
or history budget is reached. It starts over if the session expires or the
viewer's visible documentation changes.

Before each answer, Docent ranks the viewer's searchable pages for that
question. The open page gets a modest boost, and a likely follow-up can reuse
the previous question as retrieval context. Only the selected, authorized
pages enter the prompt or citation list; hidden, search-excluded, and
unauthorized content stays out. Retrieval is local and uses the same lexical
index as search—no vector database or external search service is required.

```php
'conversation' => [
    'ttl' => 7200,
    'max_turns' => 10,
    'history_budget' => 12000,
],
'retrieval' => [
    'max_pages' => 8,
    'candidate_limit' => 24,
    'debug' => false,
],
```

## Privacy-conscious insights

Docent can collect a small, first-party set of signals for improving the help
center: page views, searches and result clicks, no-click searches, Assistant
outcomes and citations, and thumbs feedback. It is off by default.

```php
'insights' => [
    'enabled' => true,
    'categories' => [
        'pages' => true,
        'search' => true,
        'assistant' => true,
    ],
    'retention_days' => 90,
    'store_query_text' => true,
    'redact_query_text' => true,
],
```

Publish and run the Docent migrations, then open **Insights** in the gated
admin panel. The dashboard highlights top pages and searches, low-click
searches, unanswered questions, and negative feedback; the same privacy-safe
events can be exported as CSV. Schedule `php artisan docent:insights:prune` to
enforce retention.

The insight table never includes a user ID, IP address, session or conversation
ID, referrer, user agent, authorization/audience context, or generated answer
text. Common sensitive patterns in query and question text are redacted, and
the value is capped at 500 characters by default; set `store_query_text` to
`false` to omit it entirely. The existing
Assistant question log is separate, so also set `ai.log_questions` to `false`
when no raw Assistant questions should be retained anywhere.

## Search that understands questions

Docent ranks search results by title, section heading, description, author
keywords, and body content. It ignores common filler words, tolerates a small
typo in longer terms, and rewards pages that cover more of the query without
requiring every word to match. Everything stays local to your application; no
search service or AI provider is involved.

When readers use a different phrase than the guide itself, add a few quiet
aliases in front matter:

```yaml
search:
  keywords:
    - insert video
    - upload a movie
```

Keywords affect ranking only. They are not rendered, included in snippets, or
added to agent-readable output. Docent accepts up to 12 keywords of 80
characters each and validates them with `docent:check`.

Question-shaped English queries use a conservative stop-word list from
`docent.search.stop_words`. Replace the list—or set it to `[]`—for another
locale.

## Validate your docs like code

```bash
php artisan docent:check          # errors exit 1
php artisan docent:check --strict # warnings fail too
```

The checker walks your whole tree and reports broken internal links, unknown values, links, conditions, components, and audiences, nonexistent named routes, missing includes and include cycles, missing images, duplicate slugs, heading hierarchy jumps, and front matter problems. Every report has a file and line number, ready for CI.

And test documentation visibility directly in your suite:

```php
use STS\Docent\Testing\InteractsWithDocs;

$this->docs()->page('billing/payment-methods')->as($admin)
    ->assertVisible()
    ->assertSee('Add a card');

$this->docs()->search('payroll', as: $member)
    ->assertMissing('Payroll Reports');
```

## The UI

A polished reading experience out of the box, with no build step in your app:

- Three-column responsive layout with grouped sidebar and scroll-spy "On this page" rail
- ⌘K ranked search with section links, typo tolerance, keyboard navigation, and highlighted snippets
- Dark mode (system-aware, persisted)
- Server-side syntax highlighting (Phiki, dual light/dark themes), copy buttons, filename labels
- One accent color rebrands everything: `config/docent.php` → `theme.accent`
- Zero external requests; ~35KB CSS + ~51KB JS, shipped prebuilt

Publish the views (`--tag=docent-views`) for deeper customization.

## Configuration

```php
// config/docent.php
return [
    'name' => env('DOCENT_NAME'),                 // defaults to "{app name} Docs"
    'route' => [
        'prefix' => 'docs',
        'domain' => null,
        'middleware' => ['web'],                  // add 'auth' for private docs
    ],
    'filesystem' => ['path' => null],             // defaults to resource_path('docs')
    'authorization' => ['denied_response' => 404], // or 403, or 'redirect:/login'
    'search' => ['enabled' => true],
    'theme' => ['accent' => '#0284c7', 'logo' => null],
];
```

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Development

This repo ships a full demo app via [Testbench Workbench](https://packages.tools/testbench):

```bash
composer install
composer serve   # boots a demo SaaS with docs at /docs
composer test
```

Log in as `/demo/login/admin` or `/demo/login/member` to see contextual documentation change with the viewer.

## License

MIT
