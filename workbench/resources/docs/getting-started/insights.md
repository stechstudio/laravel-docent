---
title: Documentation Insights
description: See where readers find help and where the docs let them down.
order: 7
---

# Documentation insights

Docent can keep a deliberately small set of first-party signals about how the
help center is used. The admin dashboard brings together popular pages and
searches, searches that rarely lead to a click, unanswered Assistant questions,
and negative feedback.

This local demo enables all three categories: pages, search, and Assistant.
The event table contains no user IDs, IP addresses, sessions, referrers, user
agents, permission context, or generated answers. Common sensitive patterns in
search and question text are redacted before storage, and the CSV export
contains only that same bounded event schema.

Both the full reader and widget emit the same versioned `docent:analytics`
browser events. That lets a host application integrate elsewhere without
giving Docent a second, broader tracking vocabulary.
