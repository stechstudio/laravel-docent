# Changelog

All notable changes to this package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Breaking changes (pre-1.0)

- Moved `agentMarkdown`, `llmsText`, `llmsFullText`, and `discoveryLinkHeader` from `DocentManager` to `STS\Docent\Content\AgentFeed`; resolve it from the container, where it is site-scoped like the manager.
- Moved the admin authoring API (`adminTree`, `adminDetail`, `filesystemSlugLocked`, `adminGroups`, `updateGroupMeta`, `removeGroupMeta`, `overrideFromFilesystem`, `draftDocument`, `tiptapError`, `exportMarkdown`, `previewDraft`, `draftIssues`, and `pickerMeta`) from `DocentManager` to `STS\Docent\Admin\Editor`; applications calling these methods on the manager or `Docent` facade must resolve the current site's `Editor` instead.
- Restructured site identity, routing, filesystem, admin, navigation, and layout configuration under `docent.sites`, with `docent.default` selecting the fallback site.
- Renamed every route from `docent.*` to the site-keyed `docent.{key}.*` form, including the shipped `docs` site.
- Added a `site` column to the shared `docent_pages`, `docent_ai_questions`, and `docent_insight_events` tables and changed page uniqueness to `(site, slug)`. Pre-release applications should rerun the published migrations.
- Admin uploads are now stored and served under a per-site namespace (`docent/{site}/…`); previously uploaded files under the flat `docent/` directory must be moved into their site's directory (e.g. `docent/docs/`) or re-uploaded.
- The `<x-docent::search-box>` and `<x-docent::hero>` components now require a `:docent` prop (the site manager); host layouts embedding them bare must pass it, e.g. `<x-docent::search-box :docent="$docent" />`.

### Added

- Multiple independent documentation sites from one installation, each with its own corpus, route prefix or domain, middleware, branding, feature switches, and admin gate.
- A lazy site registry and site-aware manager/service graphs with shared configuration defaults and explicit site-only settings.
- Global and site-scoped integration registration with site-local precedence and the current site exposed on every `DocumentationContext`.
- Site-isolated database pages, Assistant questions, insights, search indexes, caches, `llms.txt` output, uploads, and admin operations.
- Site targeting for `<x-docent::widget>` and keyed route helpers through `DocentManager`.
- `--site` selection for `docent:clear`, `docent:check`, and `docent:insights:prune`, plus whole-map definition checks for invalid sites and overlapping routes.
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

### Fixed

- Assistant answers now favor flat, readable Markdown with properly fenced code examples and clickable documentation citations.
