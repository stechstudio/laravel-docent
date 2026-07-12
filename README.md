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
- ⌘K search palette with keyboard navigation and highlighted snippets
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
