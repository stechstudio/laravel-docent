---
title: Installation
description: Install and configure the Acme Ledger SDK.
order: 2
---

# Installation

The SDK ships as a Composer package and a small set of published config files.

## 1. Require the package

```bash
composer require acme/ledger
```

## 2. Publish configuration

```bash
php artisan vendor:publish --tag=acme-ledger-config
php artisan migrate
```

## 3. Add your API credentials

Add your keys to the environment file:

```dotenv
ACME_LEDGER_KEY=pk_live_xxxxxxxxxxxxxxxx
ACME_LEDGER_SECRET=sk_live_xxxxxxxxxxxxxxxx
ACME_LEDGER_ENV=production
```

## 4. Record your first transaction

```php
use Acme\Ledger\Facades\Ledger;

Ledger::transaction(function ($tx) {
    $tx->debit('cash', 2_500_00);
    $tx->credit('revenue', 2_500_00);
    $tx->memo('Invoice #1001');
});
```

The API returns a fully-typed `Transaction` object:

```json
{
  "id": "txn_9f2b",
  "status": "posted",
  "entries": [
    { "account": "cash", "direction": "debit", "amount": 250000 },
    { "account": "revenue", "direction": "credit", "amount": 250000 }
  ]
}
```

:::warning title="Use minor units"
All amounts are integers in the currency's **minor units** (cents). Passing a
float will throw an `InvalidAmountException`.
:::

Next, work through the [quickstart](quickstart) to see reconciliation in action.
