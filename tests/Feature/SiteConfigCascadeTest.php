<?php

use STS\Docent\DocentManager;

it('site entry overrides the shared top level', function () {
    config()->set('docent.theme.accent', '#111111');
    config()->set('docent.sites.docs.theme.accent', '#ff0000');
    $this->app->forgetScopedInstances();

    expect($this->app->make(DocentManager::class)->accent())->toBe('#ff0000');
});

it('shared top level applies when the site omits the key', function () {
    config()->set('docent.search.enabled', false);
    $this->app->forgetScopedInstances();

    expect($this->app->make(DocentManager::class)->config('search.enabled', true))->toBeFalse();
});

it('exposes the selected site key', function () {
    expect($this->app->make(DocentManager::class)->key())->toBe('docs');
});

it('refreshes the site snapshot when scoped instances are forgotten', function () {
    $first = $this->app->make(DocentManager::class);
    config()->set('docent.theme.accent', '#00ff00');
    $this->app->forgetScopedInstances();
    $second = $this->app->make(DocentManager::class);

    expect($second)->not->toBe($first)
        ->and($second->accent())->toBe('#00ff00');
});
