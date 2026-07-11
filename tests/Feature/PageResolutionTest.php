<?php

use STS\Docent\DocentManager;
use STS\Docent\Facades\Docent;

it('resolves the home page from the root index', function () {
    $this->get('/docs')
        ->assertOk()
        ->assertSee('Welcome home');
});

it('resolves a nested page slug', function () {
    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('Install the thing');
});

it('resolves a directory index page to its directory slug', function () {
    $this->get('/docs/guides')
        ->assertOk()
        ->assertSee('Pick a guide');

    expect(Docent::page('guides'))->not->toBeNull();
});

it('returns 404 for an unknown slug', function () {
    $this->get('/docs/does/not/exist')->assertNotFound();

    expect(Docent::page('does/not/exist'))->toBeNull();
});

it('exposes title and description on the resolved page', function () {
    $page = app(DocentManager::class)->page('guides/setup');

    expect($page->title())->toBe('Setup')
        ->and($page->description())->toBe('Set things up.');
});
