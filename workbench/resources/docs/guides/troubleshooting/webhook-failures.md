---
title: Webhook Failures
description: Retry semantics and dead-letter handling for outbound webhooks.
order: 2
---

## Retries and dead letters

Failed webhooks retry five times with exponential backoff, then land in the
dead-letter list where you can replay them individually or in bulk.
