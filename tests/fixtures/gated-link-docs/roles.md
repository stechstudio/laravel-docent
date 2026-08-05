---
title: Roles And Permissions
description: Deliberately ungated, because the readers who need it are the ones a gate would turn away.
---

# Roles And Permissions

Read the [billing guide](billing) for details.

Or the [audience-gated notes](internal-notes).

The [open page](open) is fine for everyone.

:::can ability="billing.manage"
Declared guarantee that matches: the [billing guide](billing) again.
:::

:::audience name="internal"
Declared guarantee that matches: the [audience-gated notes](internal-notes) again.
:::

:::can ability="reports.view"
A guarantee about a different ability entirely: the [billing guide](billing).
:::

:::cannot ability="billing.manage"
A cannot block widens rather than narrows, so the [billing guide](billing) here still counts.
:::

:::when condition="beta-features"
A condition is not authorization, so the [billing guide](billing) here still counts.
:::

::::cards
:::card title="Billing" href="billing"
A card href is a link too.
:::
::::

:::include name="billing-link"
:::
