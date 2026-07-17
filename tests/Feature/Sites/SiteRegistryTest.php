<?php

use Illuminate\Http\Request;
use STS\Docent\DocentManager;
use STS\Docent\Http\Middleware\SetCurrentSite;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;
use STS\Docent\Sites\CurrentSite;
use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Sites\SiteServices;
use STS\Docent\Support\DocentCache;

function twoSiteConfig(): void
{
    config()->set('docent.default', 'public');
    config()->set('docent.sites', [
        'public' => [
            'name' => 'Help Center',
            'route' => ['prefix' => 'help', 'middleware' => ['web']],
            'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
        ],
        'admin' => [
            'name' => 'Admin Docs',
            'route' => ['prefix' => 'admin/docs', 'middleware' => ['web', 'auth']],
            'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
        ],
    ]);
}

it('builds one manager per site lazily and memoizes it', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);

    $public = $registry->site('public');
    $admin = $registry->site('admin');

    expect($public)->toBeInstanceOf(DocentManager::class)
        ->and($public->key())->toBe('public')
        ->and($admin->key())->toBe('admin')
        ->and($registry->site('public'))->toBe($public);
});

it('rejects unknown and malformed site keys', function () {
    twoSiteConfig();

    expect(fn () => $this->app->make(SiteRegistry::class)->site('nope'))
        ->toThrow(InvalidArgumentException::class, 'Unknown Docent site [nope]');

    config()->set('docent.sites', [
        ...config('docent.sites'),
        'bad.key' => ['filesystem' => ['path' => __DIR__]],
    ]);

    expect(fn () => $this->app->make(SiteRegistry::class)->keys())
        ->toThrow(InvalidArgumentException::class, 'Invalid Docent site key [bad.key]');
});

it('rejects a configured default that does not name a site', function () {
    twoSiteConfig();
    config()->set('docent.default', 'missing');

    $this->app->make(SiteRegistry::class)->defaultKey();
})->throws(InvalidArgumentException::class, 'Unknown default Docent site [missing]');

it('resolves current from the selected key and otherwise uses the default', function () {
    twoSiteConfig();
    $current = $this->app->make(CurrentSite::class);
    $services = $this->app->make(SiteServices::class);

    expect($current->key())->toBe('public')
        ->and($services->current()->key())->toBe('public');

    $current->set('admin');

    expect($services->current()->key())->toBe('admin');
});

it('gives each site its own internally consistent service graph', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);
    $publicSearch = $registry->serviceFor('public', SearchEngine::class);
    $adminSearch = $registry->serviceFor('admin', SearchEngine::class);
    $publicManager = $registry->site('public');

    expect($adminSearch)->not->toBe($publicSearch)
        ->and($registry->serviceFor('public', DocentManager::class))->toBe($publicManager)
        ->and((new ReflectionProperty(SearchEngine::class, 'manager'))->getValue($publicSearch))->toBe($publicManager)
        ->and((new ReflectionProperty(SearchEngine::class, 'indexer'))->getValue($publicSearch))
        ->toBe($registry->serviceFor('public', SearchIndexer::class))
        ->and($registry->serviceFor('public', DocentCache::class))
        ->not->toBe($registry->serviceFor('admin', DocentCache::class));
});

it('keeps site registrations after scoped instances are forgotten', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);
    $registry->site('admin')->value('plan', fn () => 'Admin');

    $this->app->forgetScopedInstances();

    expect($registry->registryFor('admin')->hasValue('plan'))->toBeTrue();
});

it('registers globally at the root and per site through site', function () {
    twoSiteConfig();
    $registry = $this->app->make(SiteRegistry::class);
    $registry->value('plan', fn () => 'Global');
    $registry->site('admin')->value('plan', fn () => 'Admin');

    $context = new DocumentationContext;

    expect($registry->registryFor('public')->resolveValue('plan', $context))->toBe('Global')
        ->and($registry->registryFor('admin')->resolveValue('plan', $context))->toBe('Admin');
});

it('selects the middleware site for the rest of the request', function () {
    twoSiteConfig();
    $current = $this->app->make(CurrentSite::class);
    $middleware = new SetCurrentSite($current);

    $result = $middleware->handle(Request::create('/'), fn () => $current->key(), 'admin');

    expect($result)->toBe('admin');
});

it('requires an explicit filesystem path for non-docs sites', function () {
    config()->set('docent.default', 'extra');
    config()->set('docent.sites', ['extra' => ['route' => ['prefix' => 'extra']]]);

    $this->app->make(SiteRegistry::class)->site('extra');
})->throws(RuntimeException::class, 'Docent site [extra] has no filesystem.path configured');
