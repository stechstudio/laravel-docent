---
title: Tokens In Code
description: A page where registered tokens are trapped inside code spans.
---

# Tokens In Code

Your plan is `{{ value:account.plan }}`, which never substitutes.

Billing lives at `{{ link:billing.settings }}`, also verbatim.

Usage so far: `{{ value:account.plan monthly }}` with an argument.

```text
{{ value:account.plan }}
```

Resolved for real: {{ value:account.plan }} and {{ link:billing.settings }}.
