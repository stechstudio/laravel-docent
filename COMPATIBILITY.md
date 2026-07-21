# Compatibility & Versioning

Docent follows [semantic versioning](https://semver.org). This document defines
what the public API is — what a minor or patch release will not break — and what
is internal and may change at any time.

## Covered by semver (safe to rely on)

- **The `Docent` facade registration DSL**: `value()`, `link()`, `condition()`,
  `audience()`, `component()`, `suggest()`, and `site()`.
- **The `Docent` facade read methods** documented on the facade:
  `page()`, `url()`, `siteName()`, `navigation()`, `contextFor()`, `registry()`.
- **Configuration keys** in `config/docent.php`. New keys may be added; existing
  keys keep their meaning within a major version.
- **Artisan commands** and their options: `docent:install`, `docent:clear`,
  `docent:check`, `docent:guide`, `docent:insights:prune`.
- **URL shapes** under a site's route prefix: `/{slug}`, `/{slug}.md`,
  `/llms.txt`, `/llms-full.txt`, `/sitemap.xml`. The `_`-prefixed paths
  (`/_search`, `/_ask`, `/_widget`, `/_assets`, `/_uploads`, `/_insights`) are
  reserved internal routes — stable as endpoints, but not user-authored.
- **The authoring dialect**: front-matter keys, dynamic tokens
  (`{{ value:… }}`, `{{ link:… }}`, `{{ route:… }}`), directives, and content
  components, as documented in the authoring guide and `docent:guide`.
- **The database schema** shipped in migrations, and the models
  `DocentPage`, `DocentPageRevision`, `AiQuestion`, `InsightEvent`. These models
  are not `final`; you may extend them for your own use.
- **Page lifecycle events**: `PageSaved`, `PagePublished`, `PageUnpublished`,
  `PageDeleted` (namespace `STS\Docent\Content\Events`).
- **The `DocumentationComponent` contract** for custom content components.
- **Publishable templates under the `docent-views` tag** (the layout, page,
  landing, and widget templates) and the view-data payload the layout receives.
- **Publishable language keys** (`docent-lang`) and the `<x-docent::widget>`
  Blade component.
- **The `window.Docent(...)` JavaScript command queue** and the
  `<x-docent::widget>` embed.

## Internal (may change in any release)

- **`DocentManager` methods marked `@internal`** — everything beyond the
  facade-documented surface above. `DocentManager` is reachable via
  `Docent::site()`, but only the documented methods are promised.
- **Blade templates published under `docent-views-internal`**, and every partial
  under `partials/**`, `widget/**`, and the `hero`/`search-box`/`section-cards`
  components. Override them at your own risk; their names and structure may
  change.
- **`data-docent-*` HTML attributes, the widget config JSON payload, and
  `window.docentUiStrings`** — build-time details of the reader/widget UI, not a
  scripting API.
- **Repository and renderer sub-interfaces** (`StoredPageRepository`,
  `LockAwareRepository`, `RedirectCollisionRepository`, `Validation\Check`) are
  internal collaborators. `DocumentationRepository`, `CodeBlockRenderer`, and
  `DocumentParser` are swappable via the container for advanced use, but their
  method sets may evolve — pin your Docent version if you replace them.
- **Any class or method annotated `@internal`.**

## Intentional naming choices

These are deliberate and stable, not oversights:

- The content element is `<docs-component name="…" />` (reads naturally for
  authors), while the registration method is `Docent::component()`.
- `docent:insights:prune` is namespaced under `insights` to leave room for
  future `docent:insights:*` commands.

## Overriding views safely

`vendor:publish --tag=docent-views` publishes only the templates intended for
branding overrides. The stable contract is the *view-data payload* those
templates receive, not the internal partials they include. If you need to fork a
partial, publish `docent-views-internal` and pin your Docent version.
