<?php

use STS\Docent\Sites\SiteConfig;

it('reads a value from the site entry first', function () {
    $config = new SiteConfig('admin', [
        'theme' => ['accent' => '#111111'],
        'sites' => ['admin' => ['theme' => ['accent' => '#ff0000']]],
    ]);

    expect($config->get('theme.accent'))->toBe('#ff0000');
});

it('cascades a shared key to the top level when the site omits it', function () {
    $config = new SiteConfig('admin', [
        'search' => ['enabled' => false],
        'sites' => ['admin' => []],
    ]);

    expect($config->get('search.enabled'))->toBeFalse();
});

it('falls back to the caller default when neither level sets a shared key', function () {
    $config = new SiteConfig('admin', ['sites' => ['admin' => []]]);

    expect($config->get('cache.prefix', 'docent'))->toBe('docent');
});

it('never cascades site-only sections to the top level', function () {
    $config = new SiteConfig('admin', [
        'name' => 'Global Name',
        'route' => ['prefix' => 'docs'],
        'sites' => ['admin' => []],
    ]);

    expect($config->get('name'))->toBeNull()
        ->and($config->get('route.prefix', 'fallback'))->toBe('fallback');
});

it('reads site-only sections from the site entry', function () {
    $config = new SiteConfig('admin', [
        'sites' => ['admin' => ['route' => ['prefix' => 'admin/docs']]],
    ]);

    expect($config->get('route.prefix'))->toBe('admin/docs');
});

it('exposes its key', function () {
    expect((new SiteConfig('docs', []))->key)->toBe('docs');
});
