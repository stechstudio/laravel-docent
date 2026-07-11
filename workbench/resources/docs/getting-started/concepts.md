---
title: Core Concepts
description: Ledgers, accounts, and entries in depth.
order: 4
---

# Core Concepts

A quick reference for the three nouns that make up every integration.

## Ledgers

A ledger is the boundary of one legal entity's books. Ledgers never share
accounts or transactions.

## Accounts

Accounts have a **type** that determines their normal balance:

| Type       | Normal balance | Example              |
| ---------- | -------------- | -------------------- |
| Asset      | Debit          | Cash, Accounts Rec.  |
| Liability  | Credit         | Loans, Accounts Pay. |
| Revenue    | Credit         | Sales, Interest      |
| Expense    | Debit          | Salaries, Hosting    |

## Entries

An entry is one side of a transaction. A transaction needs at least two entries
and must balance:

- Nested detail is supported for line items.
    - Each line item can carry its own metadata.
    - Metadata is indexed and searchable.
- Entries are immutable once posted.

Ready to accept payments? Head to the [billing overview](../billing/overview).
