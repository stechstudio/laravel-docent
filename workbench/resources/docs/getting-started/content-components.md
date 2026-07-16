---
title: Content Components
description: Interactive patterns for task-focused guides and visual explanations.
order: 5
search:
  keywords:
    - insert video
    - embed a video
---

# Content Components

Use these components to make a guide easier to scan without hiding its meaning
from search, print, or agent-readable Markdown.

## Move a page safely

After moving a guide, replace the old file with a redirect stub that points to
the new internal slug:

```markdown
---
title: Previous setup guide
redirect: getting-started/quickstart
---
```

The old URL now returns a permanent redirect for viewers who may open the new
page. It preserves query strings, while URL fragments remain in the browser
and are not sent to the server. The stub itself does not appear in navigation,
search, agent-readable output, Assistant retrieval, or widget suggestions.

Run the checker after every move:

```text
$ php artisan docent:check
Docent looks great — no problems found in 11 pages.
```

If one stub points to another, the checker reports the full chain so you can
point the oldest slug directly at the final page.

## Complete a setup

::::steps
:::step Install the package
Run Composer from your application directory:

```bash
composer require stechstudio/laravel-docent
```
:::
:::step Publish the configuration
Publish Docent's configuration file, then adjust the route and branding for
your application.

:::can billing.manage
Administrators can also enable the contextual billing guide from this screen.
:::
:::
:::step Add your first guide
Create a Markdown file in `resources/docs` and give it a title in front matter.
:::
::::

## Answer common questions

:::accordion How do refunds work?
Refunds return to the original payment method. Most banks show the credit within
five to ten business days.

### Refund timing

The exact timing depends on the customer's bank, not the day the refund was
requested.
:::
:::accordion Can I cancel a pending refund?
No. Once submitted, a refund cannot be cancelled.
:::

## Compare platforms

::::tabs
:::tab iOS
Install the app from the App Store, then sign in with your workspace email.

### iOS notifications

Allow notifications when prompted so approval requests can reach you.
:::
:::tab Android
Install the app from Google Play, then sign in with your workspace email.

### Android notifications

Keep battery optimization disabled for time-sensitive approval requests.
:::
::::

## Show the interface

:::frame caption="The billing overview screen"
![A sample billing overview with plan and invoice details](https://placehold.co/1200x720/eff6ff/1e3a8a?text=Billing+overview)
:::

:::can reports.view
:::accordion Where can administrators find exports?
Open **Reports**, choose an export, and select the date range you need.
:::
:::

## Watch a walkthrough

Provider videos stay quiet until someone presses play, so opening this guide
does not contact the video host.

:::video https://www.youtube.com/watch?v=dQw4w9WgXcQ caption="See how to reconcile a ledger"
:::

Self-hosted clips use the browser's standard video controls.

:::video /demo/reconciling.mp4 caption="Reconciling a ledger in 90 seconds"
:::

## Compare code examples

Use a code group when readers need the same task in more than one language or
tool. Each example remains available in print, search, and agent-readable
Markdown.

::::code-group
```php filename="routes/web.php"
Route::get('/billing', BillingController::class);
```
```bash title="Terminal"
php artisan route:list --path=billing
```
::::
