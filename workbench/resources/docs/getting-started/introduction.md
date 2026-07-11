---
title: Introduction
description: A five-minute tour of how Acme Ledger is put together.
order: 1
---

# Introduction

Acme Ledger models your finances as a stream of **immutable transactions** posted
to **accounts**. Every transaction balances to zero — money always comes from
somewhere and goes somewhere.

## The mental model

1. **Accounts** are buckets: cash, revenue, expenses, liabilities.
2. **Transactions** move value between accounts as balanced entries.
3. **Reports** are just queries over that transaction history.

> Because transactions are immutable, your books are always auditable. You never
> edit history — you post a correcting entry.

## Core concepts

There are only three nouns you need to hold in your head:

- **Ledger** — the top-level container for one legal entity.
- **Account** — a named bucket inside a ledger.
- **Entry** — one side of a transaction (a debit or a credit).

Continue to the [installation guide](installation) to add the SDK to your
application.
