<?php

use Illuminate\Support\Facades\Cache;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Support\DocentCache;

it('parses a page only once and serves the AST from cache thereafter', function () {
    $real = new MarkdownDocumentParser;

    $spy = Mockery::mock(DocumentParser::class);
    $spy->shouldReceive('parse')->once()->andReturnUsing(fn (string $content) => $real->parse($content));

    $this->app->instance(DocumentParser::class, $spy);

    $manager = app(DocentManager::class);

    $manager->page('guides/setup')->document();
    $manager->page('guides/setup')->document();

    // Mockery's ->once() assertion is verified on teardown.
    expect(true)->toBeTrue();
});

it('serves docs from cache on stores that refuse to unserialize objects', function () {
    // Fresh Laravel apps ship cache.serializable_classes = false: the store
    // will not unserialize any object. Docent must still round-trip its
    // object-bearing caches (nav skeleton, ASTs, search records).
    config()->set('cache.serializable_classes', false);
    app('cache')->forgetDriver(config('cache.default'));

    $this->get('/docs/guides/setup')->assertOk();
    $this->get('/docs/guides/setup')->assertOk();
    $this->getJson('/docs/_search?q=setup')->assertOk();
});

it('allowlists every AST class for cache round-trips', function () {
    $classes = collect(glob(dirname(__DIR__, 2).'/src/Documents/Ast/*.php'))
        ->map(fn (string $file): string => 'STS\\Docent\\Documents\\Ast\\'.basename($file, '.php'));

    expect($classes)->not->toBeEmpty();

    $classes->each(fn (string $class) => expect(DocentCache::ALLOWED_CLASSES)->toContain($class));
});

it('treats cached payloads referencing foreign classes as misses', function () {
    // A stdClass is not allowlisted; the poisoned entry must be recomputed,
    // not surfaced as __PHP_Incomplete_Class.
    Cache::forever('docent:docs:1:probe', serialize((object) ['x' => 1]));

    expect(app(DocentCache::class)->remember('probe', fn (): string => 'recomputed'))->toBe('recomputed');
});

it('bumps the cache version stamp when cleared', function () {
    $cache = app(DocentCache::class);

    $before = $cache->version();

    $this->artisan('docent:clear')->assertSuccessful();

    expect($cache->version())->toBe($before + 1);
});
