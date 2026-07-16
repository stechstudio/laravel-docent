# Releasing

This document is for maintainers. It describes how a release is cut; it does
not grant anyone release authority — the maintainer owns release timing.

## Versioning

The package follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html):

- **Patch** (`x.y.Z`): bug fixes and internal changes with no public-surface impact.
- **Minor** (`x.Y.0`): new features, new configuration keys, new directives — anything additive.
- **Major** (`X.0.0`): breaking changes to config shape, published views, Blade component contracts, front matter semantics, or supported PHP/Laravel versions.

While the package is pre-1.0, minor releases may contain breaking changes;
they must be called out explicitly in the changelog.

## Changelog discipline

`CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Every user-visible change lands in the `Unreleased` section as part of the PR
that makes the change. Cutting a release renames `Unreleased` to the new
version with the release date and opens a fresh `Unreleased` section.

## Cutting a release

1. Confirm CI is green on `main` (tests, quality, assets, static analysis).
2. Confirm the committed `resources/dist` output matches source: `npm run build && git diff --exit-code -- resources/dist`.
3. Move the `Unreleased` changelog entries under the new version heading with today's date, and commit.
4. Tag the release:

   ```bash
   git tag -a v0.1.0 -m "v0.1.0"
   git push origin main --tags
   ```

5. Package publication: the package is distributed through the Composer
   ecosystem's standard public registry. First-time setup is a one-time
   submission of the repository URL there; after that, each pushed tag is
   picked up automatically via the registry's GitHub integration. Verify the
   new version appears and installs: `composer require stechstudio/laravel-docent`.

Release archives are trimmed by `.gitattributes` `export-ignore` rules; after
changing packaging-relevant files, sanity-check the archive contents with
`git archive HEAD | tar -t`.
