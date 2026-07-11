---
title: Reports
description: Financial reporting for account admins.
authorize: reports.view
order: 1
---

# Reports

This section is restricted to users who can view financial reports. If you can
read this, you have the **reports.view** ability.

## Available reports

- **Profit &amp; Loss** — revenue and expenses over a period.
- **Balance Sheet** — assets, liabilities, and equity at a point in time.
- **Cash Flow** — money in and money out.

```php
$report = Ledger::reports()->profitAndLoss(
    from: now()->startOfYear(),
    to: now(),
);
```

:::tip title="Scheduling"
Any report can be scheduled and emailed to stakeholders on a recurring basis.
:::
