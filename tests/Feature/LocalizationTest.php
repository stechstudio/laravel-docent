<?php

it('renders the reader UI in the application locale', function () {
    app('translator')->addLines([
        'ui.search.placeholder' => 'Rechercher…',
    ], 'fr', 'docent');

    app()->setLocale('fr');

    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('Rechercher…');

    app()->setLocale('en');

    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('Search documentation…');
});
