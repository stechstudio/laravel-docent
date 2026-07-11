---
title: Payment Methods
description: Add, update, and remove payment methods.
order: 2
---

# Payment Methods

You can store multiple payment methods and designate one as the default.

:::include name="permissions-note"

## Your current usage

<docs-component name="plan-usage" plan="team" />

## Add a card

Cards are tokenized in the browser and never touch your servers:

```php
Ledger::billing()->addPaymentMethod($request->string('token'));
```

## Manage billing

:::can ability="billing.manage"
As a billing administrator you can change the default card, download invoices,
and update the plan directly from
[billing settings]({{ link:billing.settings }}).
:::

:::cannot ability="billing.manage"
You can view payment methods, but changing them requires the **billing manager**
role. Ask an account owner to update your permissions.
:::

:::when condition="beta-features"
You are in the beta program, so ACH bank transfers are also available as a
payment method.
:::
