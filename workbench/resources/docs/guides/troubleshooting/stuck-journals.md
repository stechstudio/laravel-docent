---
title: Stuck Journals
description: What to do when a journal sits in the posting queue.
order: 1
---

## Diagnosing a stuck journal

A journal that sits in `posting` for more than a minute is usually waiting on
a lock held by a reconciliation run. Check the queue dashboard first, then
retry the journal from its detail page.
