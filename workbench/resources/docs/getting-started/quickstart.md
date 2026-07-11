---
title: Quickstart
description: Record, reconcile, and report in five minutes.
order: 3
---

# Quickstart

This quickstart walks through the happy path end to end. Follow along in a
scratch ledger — nothing here touches production.

:::note
Every code sample assumes you have completed the [installation](installation)
steps and can resolve the `Ledger` facade.
:::

## Your checklist

- [x] Install the SDK
- [x] Add API credentials
- [ ] Post a transaction
- [ ] Reconcile a bank feed
- [ ] Pull a report

## Post a transaction

```php
$tx = Ledger::transaction(fn ($tx) => $tx
    ->debit('cash', 10_00)
    ->credit('sales', 10_00)
    ->memo('Coffee sale'));
```

:::tip title="Idempotency"
Pass an `idempotencyKey` to safely retry a request without double-posting.
:::

## Reconcile a bank feed

Connected feeds are matched automatically, but you can force a match:

```php
Ledger::reconcile($tx, bankTransactionId: 'bank_7712');
```

:::info title="Automatic matching"
Most transactions reconcile on their own within a few minutes of the bank feed
syncing. You only need to intervene for ambiguous matches.
:::

## Common pitfalls

:::danger title="Never reuse ledgers across entities"
Each legal entity **must** have its own ledger. Mixing entities produces
reports that will not survive an audit.
:::

Once you are comfortable here, configure [billing](../billing/overview) so your
customers can pay you.
