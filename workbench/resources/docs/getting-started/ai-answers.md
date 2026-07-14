---
title: AI Answers
description: Try a grounded answer drawn only from the help available to you.
order: 6
---

# AI Answers

Docent can answer questions from the pages the current reader is allowed to
see. Search stays focused on finding pages; the Assistant gets its own
full-height space for slower, more detailed answers and follow-up questions.

Open search, type **How do I insert a video?**, then choose **Ask Assistant** at
the top or beneath the results. You can also open an empty Assistant from the
top bar or with **Cmd/Ctrl+I**. Follow a source and the completed answer remains
open while you read. Ask a follow-up and the Assistant uses the completed turns
from this temporary help session as context. The help widget uses the same
handoff without nesting a second drawer inside the widget.

The response supports headings, lists, inline code, fenced code, and
blockquotes. Model links become clickable only when they exactly match a page
Docent made available to the current viewer.

Use the trash button to start over. Conversations expire after two hours by
default and reset automatically if the documentation available to the viewer
changes. They are kept in Laravel's cache, not in a permanent transcript table.

:::note title="Local demo"
This workbench uses Prism's local fake provider. It demonstrates streaming,
formatted code, citations, follow-up memory, persistence, and feedback without
an API key or an external request.
:::
