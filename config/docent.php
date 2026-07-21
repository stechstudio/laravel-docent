<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Site
    |--------------------------------------------------------------------------
    |
    | The site resolved when no explicit site key is selected.
    |
    */

    'default' => 'docs',

    // ── Shared defaults (overridable per site) ──────────────────────────────

    /*
    |--------------------------------------------------------------------------
    | Database Store
    |--------------------------------------------------------------------------
    |
    | Opt-in database-backed pages, composed *over* the filesystem (a database
    | page overrides a file with the same slug). Publish the tables with
    | `php artisan docent:install --with-database` (or
    | `vendor:publish --tag=docent-migrations`), migrate, then flip `enabled`.
    | `connection` is null for the default database connection.
    |
    */

    'database' => [
        'enabled' => false,
        'connection' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    |
    | Response returned when a viewer is denied a page (front matter authorize
    | / audience). Options: 404 (hide existence, default), 403, or a redirect
    | string "redirect:/login".
    |
    */

    'authorization' => [
        'denied_response' => 404,
    ],

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    |
    | Repository Markdown is reviewed application code, so its raw HTML remains
    | enabled by default and keeps the historical `allow_html` setting. Content
    | written through the database admin is sanitized when rendered: ordinary
    | structural HTML stays useful, while scripts, event handlers, unsafe URLs,
    | and other active content are removed. The original source remains stored
    | verbatim.
    | Deploy-trusted teams may explicitly disable database sanitization.
    |
    */

    'content' => [
        'allow_html' => true,
        'database' => [
            'sanitize_html' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engine Optimization
    |--------------------------------------------------------------------------
    */

    'seo' => [
        // Serve {prefix}/sitemap.xml listing the pages a guest may see.
        'sitemap' => true,

        // A social-preview image for link unfurls (path or absolute URL).
        // Pages can override it with an `image:` front matter key.
        'image' => null,
    ],

    'check' => [
        // Override a rule's severity by its stable id, or silence it with 'off'.
        // Opt-in quality rules run only when listed here as error/warning/warn.
        // e.g. 'heading-hierarchy' => 'warning', 'search-keywords' => 'off'.
        // Enable one with e.g. 'single-h1' => 'warning'.
        'rules' => [
            // 'unknown-icon' => 'warning',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    'search' => [
        'enabled' => true,
        // English defaults keep question-shaped queries focused. Replace this
        // list (or set it to []) when the documentation uses another locale.
        'stop_words' => [
            'a', 'an', 'and', 'are', 'can', 'could', 'do', 'does', 'for', 'from',
            'how', 'i', 'in', 'is', 'it', 'my', 'of', 'on', 'or', 'our', 'should',
            'that', 'the', 'this', 'to', 'we', 'what', 'when', 'where', 'which',
            'with', 'would', 'you', 'your',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ask the Docs
    |--------------------------------------------------------------------------
    |
    | Optional grounded answers powered by the host application's Prism setup.
    | The feature is off by default and Prism is intentionally not a required
    | package dependency. Questions use only the current viewer's visible docs.
    |
    */

    'ai' => [
        'enabled' => false,
        'provider' => env('DOCENT_AI_PROVIDER'),
        'model' => env('DOCENT_AI_MODEL'),
        // null = answer in the docs' language (today's behavior).
        // 'viewer' = answer in the app locale of the request (app()->getLocale()).
        // Any locale string (e.g. 'de', 'pt-BR') = always answer in that language.
        'language' => null,
        'log_questions' => true,
        'max_tokens' => 1200,
        'throttle' => '10,1',
        'corpus_budget' => 150000,
        'answer_ttl' => 300,
        'retrieval' => [
            'max_pages' => 8,
            'candidate_limit' => 24,
            'debug' => false,
        ],
        'conversation' => [
            'ttl' => 7200,
            'max_turns' => 10,
            'history_budget' => 12000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Insights
    |--------------------------------------------------------------------------
    |
    | Optional, first-party signals for improving the help center. Events use a
    | fixed schema and never store user IDs, IPs, sessions, referrers, user
    | agents, audience/gate context, or generated answer text. Common sensitive
    | patterns in search and question text are redacted before storage.
    |
    */

    'insights' => [
        'enabled' => false,
        'categories' => [
            'pages' => true,
            'search' => true,
            'assistant' => true,
        ],
        'retention_days' => 90,
        'store_query_text' => true,
        'redact_query_text' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | In-app Help Widget
    |--------------------------------------------------------------------------
    |
    | Render the launcher explicitly with <x-docent::widget />. The iframe is
    | same-origin and therefore inherits the docs route middleware and viewer.
    |
    */

    'widget' => [
        'enabled' => false,
        'mode' => 'overlay',
        'position' => 'right',
        'offset' => 24,
        'launcher' => 'button',
        'icon' => 'book-open',
        'preload' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Store used for the parsed AST, navigation skeleton, and search index.
    | Null uses the default cache store. `docent:clear` bumps a version stamp
    | folded into every key, so no store-specific tagging is required.
    |
    */

    'cache' => [
        'store' => null,
        'prefix' => 'docent',
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Brand the documentation UI entirely from config — no CSS rebuild, no
    | published views. Every token below flows through runtime CSS variables.
    |
    | `accent` is a single hex colour driving every accent (active nav, links,
    | focus rings, search highlights). `logo` is a path or URL shown in the top
    | bar; null falls back to a text wordmark. `logo_dark` swaps it in dark mode
    | (falls back to `logo`); `logomark` is a square mark used in the compact
    | mobile header (falls back to `logo` → wordmark). `favicon` is emitted as a
    | <link rel="icon"> when set.
    |
    | `font.sans` / `font.mono` are CSS font-family stacks (null keeps the
    | system stack); `font.href` optionally emits a webfont stylesheet link
    | (default is zero external requests).
    |
    | `gray` selects the base palette temperature (slate | zinc | stone |
    | neutral) and `radius` the corner feel (sharp | default | soft).
    |
    */

    'theme' => [
        'accent' => '#0284c7',
        'logo' => null,
        'logo_dark' => null,
        'logomark' => null,
        'favicon' => null,
        'font' => [
            'sans' => null,
            'mono' => null,
            'href' => null,
        ],
        'gray' => 'slate',
        'radius' => 'default',
    ],

    // ── Sites ───────────────────────────────────────────────────────────────

    /*
    |--------------------------------------------------------------------------
    | Documentation Sites
    |--------------------------------------------------------------------------
    |
    | Site keys are chosen by the host application. Any shared section above
    | may be repeated inside a site entry to override its defaults. Site-only
    | sections (name, description, route, filesystem, admin, navigation, and
    | layouts) live exclusively inside each site entry.
    |
    */

    'sites' => [
        'docs' => [
            /*
            |----------------------------------------------------------------------
            | Site Name
            |----------------------------------------------------------------------
            |
            | The name shown in the documentation UI (title bar, header). Defaults
            | to your application name suffixed with "Docs".
            |
            */

            'name' => env('DOCENT_NAME', config('app.name').' Docs'),

            // Used by llms.txt and available to host applications for metadata.
            'description' => env('DOCENT_DESCRIPTION'),

            /*
            |----------------------------------------------------------------------
            | Route
            |----------------------------------------------------------------------
            |
            | The docs are served under this prefix (and optional domain), guarded
            | by the middleware. For authenticated docs, use ['web', 'auth'].
            |
            */

            'route' => [
                'prefix' => 'docs',
                'domain' => null,
                'middleware' => ['web'],
            ],

            /*
            |----------------------------------------------------------------------
            | Filesystem
            |----------------------------------------------------------------------
            |
            | Where the markdown documents live. Null resolves to
            | resource_path('docs').
            |
            */

            'filesystem' => [
                'path' => null,
            ],

            /*
            |----------------------------------------------------------------------
            | Admin Panel
            |----------------------------------------------------------------------
            |
            | The database-backed authoring panel, served under `path` inside the
            | docs route group (so `/docs/admin` by default — a docs page with that
            | exact slug would be shadowed by the panel). Requires the database
            | store (`docent.database.enabled`) and is off by default. Every admin
            | route — the panel and its JSON API — is additionally guarded by the
            | `gate` ability, which the host application defines (it denies guests
            | by default). Image uploads land on `disk` and are served back through
            | the docs `_uploads` route — any disk works (public, local, private
            | S3), no storage:link or public bucket required, and images inherit
            | the docs route middleware. Uploaded files use private immutable
            | browser caching by default. Enable `uploads.public_cache` only when
            | the documentation and every uploaded image are intentionally public
            | through any shared cache.
            |
            */

            'admin' => [
                'enabled' => false,
                'path' => 'admin',
                'gate' => 'viewDocentAdmin',
                'disk' => 'public',
                'uploads' => [
                    'public_cache' => false,
                ],
            ],

            /*
            |----------------------------------------------------------------------
            | Navigation
            |----------------------------------------------------------------------
            |
            | Top-level directories whose `_group.yml` contains `section: true`
            | become switchable documentation areas. Everything else remains in
            | the default section. Persistent links appear above the sidebar and
            | may target an external URL, a documentation page, or a named route.
            | Topbar links use the same shape but render as icon buttons beside
            | the theme toggle.
            |
            */

            'navigation' => [
                'default_section' => 'Documentation',
                'links' => [
                    // ['label' => 'Support', 'icon' => 'lifebuoy', 'url' => 'https://example.com/support'],
                    // ['label' => 'Setup guide', 'icon' => 'rocket-launch', 'page' => 'getting-started/setup'],
                    // ['label' => 'Admin console', 'icon' => 'wrench', 'route' => 'admin.dashboard', 'can' => 'admin'],
                ],
                'topbar' => [
                    // ['label' => 'GitHub', 'icon' => 'github', 'url' => 'https://github.com/acme/acme'],
                ],
            ],

            /*
            |----------------------------------------------------------------------
            | Page Layouts
            |----------------------------------------------------------------------
            |
            | A page opts out of the docs chrome with front-matter `layout: <name>`.
            | Names resolve to `docent::layouts.<name>` views, so a custom layout
            | is one new file in resources/views/vendor/docent/layouts/ — no
            | package views need forking. Map a name here instead to point it at
            | any view in your application. Every layout receives the same
            | documented payload as the built-in `landing` layout.
            |
            */

            'layouts' => [
                // 'help-center' => 'docs.help-center',
            ],
        ],
    ],

];
