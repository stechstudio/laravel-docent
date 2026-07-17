<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use STS\Docent\DocentServiceProvider;
use STS\Docent\Http\Middleware\SetCurrentSite;

it('registers named docs routes with the configured prefix and middleware', function () {
    $home = Route::getRoutes()->getByName('docent.docs.home');
    $show = Route::getRoutes()->getByName('docent.docs.show');

    expect($home)->not->toBeNull()
        ->and($show)->not->toBeNull()
        ->and($home->uri())->toBe('docs')
        ->and($show->uri())->toBe('docs/{slug}')
        ->and($home->gatherMiddleware())->toContain('web', SetCurrentSite::class.':docs')
        ->and(Route::has('docent.home'))->toBeFalse();
});

it('registers isolated keyed routes and content for every configured site', function () {
    config()->set('docent.default', 'public');
    config()->set('docent.sites', [
        'public' => [
            'name' => 'Public Help',
            'route' => ['prefix' => 'help', 'middleware' => ['web']],
            'filesystem' => ['path' => dirname(__DIR__).'/fixtures/docs'],
        ],
        'admin' => [
            'name' => 'Admin Help',
            'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
            'filesystem' => ['path' => dirname(__DIR__).'/fixtures/clean-docs'],
        ],
    ]);

    $this->app['router']->setRoutes(new RouteCollection);
    (new DocentServiceProvider($this->app))->boot();
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('docent.public.home')?->uri())->toBe('help')
        ->and(Route::getRoutes()->getByName('docent.admin.home')?->uri())->toBe('admin/docs')
        ->and(Route::getRoutes()->getByName('docent.public.show')?->gatherMiddleware())
        ->toContain(SetCurrentSite::class.':public')
        ->and(Route::getRoutes()->getByName('docent.admin.show')?->gatherMiddleware())
        ->toContain(SetCurrentSite::class.':admin');

    $this->get('/help')->assertOk()->assertSee('Welcome home');
    $this->resetDocentScope();
    $this->get('/admin/docs')->assertOk()->assertSee('Read the');
});

it('honors a custom route prefix from config', function () {
    config()->set('docent.sites.docs.route.prefix', 'handbook');

    (new DocentServiceProvider($this->app))->boot();
    Route::getRoutes()->refreshNameLookups();

    $this->get('/handbook')->assertOk()->assertSee('Welcome home');
    $this->get('/handbook/guides/setup')->assertOk();
    $this->get('/handbook/guides/setup.md')->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
    $this->get('/handbook/llms.txt')->assertOk();
});
