---
title: Rollbacks
description: Roll a bad release back without losing posted entries.
order: 2
---

## The rollback window

Every release keeps a fifteen-minute rollback window. Posted journal entries
are never rolled back — only code and configuration — so a rollback is always
safe to run.
