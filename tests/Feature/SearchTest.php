<?php

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentServiceProvider;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;
use STS\Docent\Search\SearchResult;
use STS\Docent\Support\DocentCache;

function slugsOf(array $results): array
{
    return array_map(fn (SearchResult $r): string => $r->slug, $results);
}

function searchAs($testCase, string $query, $user = null): array
{
    return app(SearchEngine::class)->search($query, $testCase->contextFor($user));
}

// -- Index build + caching -------------------------------------------------

it('builds one record per visible page, skipping hidden and search-excluded pages', function () {
    $records = app(SearchIndexer::class)->records();
    $slugs = array_map(fn ($r) => $r->slug, $records);

    // Gated pages are still indexed (the index is context-free); hidden and
    // search-excluded pages are not.
    expect($slugs)->toContain('billing/secret')
        ->and($slugs)->toContain('billing/overview')
        ->and($slugs)->not->toContain('changelog')      // search.exclude
        ->and($slugs)->not->toContain('guides/advanced'); // hidden
});

it('caches the index under the repository directory hash', function () {
    app(SearchIndexer::class)->records();

    $hash = app(DocumentationRepository::class)->directoryHash();

    // remember() returns the already-stored value; the sentinel proves the
    // index was written under exactly this directory-hash key.
    $cached = app(DocentCache::class)->remember('search:'.$hash, fn () => 'MISS');

    expect($cached)->not->toBe('MISS');
});

// -- Scoring ----------------------------------------------------------------

it('ranks a title match above a body-only match', function () {
    $results = searchAs($this, 'billing');

    // "Billing Overview" (title) must outrank "Setup" (body-only "billing settings").
    expect($results[0]->slug)->toBe('billing/overview')
        ->and(slugsOf($results))->toContain('guides/setup');
});

it('requires every token to match somewhere (AND semantics)', function () {
    $results = slugsOf(searchAs($this, 'billing overview'));

    // "Billing Overview" has both tokens; "Setup" has only "billing".
    expect($results)->toContain('billing/overview')
        ->and($results)->not->toContain('guides/setup');
});

it('matches on token prefixes', function () {
    $results = slugsOf(searchAs($this, 'bill'));

    expect($results)->toContain('billing/overview');
});

it('returns nothing for empty or too-short queries', function () {
    expect(searchAs($this, ''))->toBe([])
        ->and(searchAs($this, 'a'))->toBe([]);
});

// -- Snippets ---------------------------------------------------------------

it('wraps matched terms in <mark> and escapes the surrounding text first', function () {
    $overview = collect(searchAs($this, 'billing'))->firstWhere('slug', 'billing/overview');

    // The body "Invoices & receipts..." must be escaped before marks are added:
    // the ampersand becomes &amp; while the mark tag itself stays literal.
    expect($overview->snippet)->toContain('<mark>')
        ->and($overview->snippet)->toContain('&amp;')
        ->and($overview->snippet)->not->toContain('Invoices & receipts');
});

it('exposes the matched heading anchor for deep linking', function () {
    // "Details" is the only h2 in guides/setup; a heading match exposes its anchor.
    $setup = collect(searchAs($this, 'details'))->firstWhere('slug', 'guides/setup');

    expect($setup?->heading)->toBe('details');
});

// -- Leakage battery --------------------------------------------------------

it('never surfaces a gated page to a guest', function () {
    $guest = slugsOf(searchAs($this, 'secret'));

    expect($guest)->not->toContain('billing/secret');

    // And nothing of the gated page's title leaks through the HTTP endpoint.
    $this->get('/docs/_search?q=secret')
        ->assertOk()
        ->assertDontSee('Secret Billing');
});

it('lets an authorized viewer find a gated page', function () {
    $admin = slugsOf(searchAs($this, 'secret', $this->adminUser()));

    expect($admin)->toContain('billing/secret');

    $this->actingAs($this->adminUser())
        ->get('/docs/_search?q=secret')
        ->assertOk()
        ->assertSee('Secret Billing');
});

it('keeps conditional-block content out of every record, even for authorized viewers', function () {
    foreach (app(SearchIndexer::class)->records() as $record) {
        // :::can and :::when bodies from guides/setup must never be indexed.
        expect($record->body)->not->toContain('You can manage billing')
            ->and($record->body)->not->toContain('Beta features are enabled');
    }

    // A term that only appears inside a conditional block finds nothing.
    expect(searchAs($this, 'beta', $this->adminUser()))->toBe([]);
});

it('keeps dynamic values out of every record', function () {
    foreach (app(SearchIndexer::class)->records() as $record) {
        expect($record->body)->not->toContain('Team Plan');
    }
});

it('respects search.exclude front matter', function () {
    // "flibbertigibbet" only appears in the search-excluded changelog page.
    expect(searchAs($this, 'flibbertigibbet'))->toBe([])
        ->and(searchAs($this, 'flibbertigibbet', $this->adminUser()))->toBe([]);
});

// -- Endpoint ---------------------------------------------------------------

it('returns JSON results with the echoed query', function () {
    $this->get('/docs/_search?q=billing')
        ->assertOk()
        ->assertJsonStructure(['results' => [['slug', 'url', 'title', 'group', 'snippet', 'heading']], 'query'])
        ->assertJsonPath('query', 'billing');
});

it('returns empty results for a short query at the endpoint', function () {
    $this->get('/docs/_search?q=a')
        ->assertOk()
        ->assertExactJson(['results' => [], 'query' => 'a']);
});

it('does not register the search route when search is disabled', function () {
    config()->set('docent.search.enabled', false);

    // Re-register the docs routes from scratch with search disabled.
    $this->app['router']->setRoutes(new RouteCollection);
    (new DocentServiceProvider($this->app))->boot();
    $this->app['router']->getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('docent.search'))->toBeNull();

    // The wildcard page route now swallows the path and 404s.
    $this->get('/docs/_search?q=billing')->assertNotFound();
});
