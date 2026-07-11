<?php

use Illuminate\Support\Facades\Route;
use STS\Docent\DocentServiceProvider;

it('registers named docs routes with the configured prefix and middleware', function () {
    $home = Route::getRoutes()->getByName('docent.home');
    $show = Route::getRoutes()->getByName('docent.show');

    expect($home)->not->toBeNull()
        ->and($show)->not->toBeNull()
        ->and($home->uri())->toBe('docs')
        ->and($show->uri())->toBe('docs/{slug}')
        ->and($home->gatherMiddleware())->toContain('web');
});

it('honors a custom route prefix from config', function () {
    config()->set('docent.route.prefix', 'handbook');

    (new DocentServiceProvider($this->app))->boot();
    Route::getRoutes()->refreshNameLookups();

    $this->get('/handbook')->assertOk()->assertSee('Welcome home');
    $this->get('/handbook/guides/setup')->assertOk();
});
