<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site Name
    |--------------------------------------------------------------------------
    |
    | The name shown in the documentation UI (title bar, header). Defaults to
    | your application name suffixed with "Docs".
    |
    */

    'name' => env('DOCENT_NAME', config('app.name').' Docs'),

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | The docs are served under this prefix (and optional domain), guarded by
    | the given middleware. For authenticated docs, add 'auth': ['web', 'auth'].
    |
    */

    'route' => [
        'prefix' => 'docs',
        'domain' => null,
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem
    |--------------------------------------------------------------------------
    |
    | Where the markdown documents live. Null resolves to resource_path('docs').
    |
    */

    'filesystem' => [
        'path' => null,
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
    | Whether raw HTML authored in repository markdown is emitted. Repository
    | content is app code reviewed in PRs, so this defaults to true.
    |
    */

    'content' => [
        'allow_html' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    'search' => [
        'enabled' => true,
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
    | Reserved for the Milestone 8 UI (logo, colors).
    |
    */

    'theme' => [],

];
