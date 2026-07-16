# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- In-app documentation site rendered inside the host application's runtime, with authentication, gates, and policies applied to pages, navigation, and search.
- Markdown authoring with YAML front matter, audience-conditional blocks, dynamic values, named-route app links, includes, and embedded Blade components.
- Structural directives for task-focused guides: callouts, steps, tabs, accordions, code groups, frames, and video embeds.
- Ranked full-text search with section-level results, typo tolerance, keyboard navigation, and permission-aware filtering.
- `docent:check` command validating links, values, routes, includes, images, slugs, and front matter, with `--strict` mode for CI.
- Testing helpers (`InteractsWithDocs`) for asserting documentation visibility and search results in the host app's suite.
- Browser-based admin editor for authoring and organizing documentation, with image uploads sanitized at storage time.
- Optional AI assistant answering viewer questions from the documentation corpus, with temporary conversations and an embeddable help widget.
- Privacy-conscious documentation insights: top searches, low click-through queries, and page engagement.
- Prebuilt, themeable UI with dark mode and server-side syntax highlighting; no build step required in the host app.
