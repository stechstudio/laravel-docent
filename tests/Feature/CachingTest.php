<?php

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

it('bumps the cache version stamp when cleared', function () {
    $cache = app(DocentCache::class);

    $before = $cache->version();

    $this->artisan('docent:clear')->assertSuccessful();

    expect($cache->version())->toBe($before + 1);
});
