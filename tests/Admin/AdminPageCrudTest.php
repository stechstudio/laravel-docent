<?php

use STS\Docent\Content\Models\DocentPage;

beforeEach(function () {
    $this->actingAs($this->adminUser());
});

it('creates a draft that shows in the tree but is invisible to readers until published', function () {
    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'new-page',
        'title' => 'New Page',
        'content' => "# New Page\n\nHello.",
    ])->assertCreated()
        ->assertJsonPath('slug', 'new-page')
        ->assertJsonPath('store', 'database')
        ->assertJsonPath('published', false)
        ->assertJsonPath('title', 'New Page');

    // In the tree...
    $this->getJson('/docs/admin/api/tree')
        ->assertJsonFragment(['slug' => 'new-page', 'store' => 'database', 'published' => false]);

    // ...but a reader cannot open it yet.
    $this->get('/docs/new-page')->assertNotFound();

    // Publish, and it goes live.
    $this->postJson('/docs/admin/api/pages/new-page/publish')
        ->assertOk()
        ->assertJsonPath('published', true);

    $this->get('/docs/new-page')->assertOk()->assertSee('Hello.');
});

it('snapshots a revision on every update and tracks unpublished changes', function () {
    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'evolving',
        'title' => 'Evolving',
        'content' => 'First draft.',
    ])->assertCreated();

    $this->postJson('/docs/admin/api/pages/evolving/publish')->assertOk();

    $this->putJson('/docs/admin/api/pages/evolving', [
        'title' => 'Evolving',
        'content' => 'Second draft.',
    ])->assertOk()
        ->assertJsonPath('hasUnpublishedChanges', true);

    $this->getJson('/docs/admin/api/pages/evolving/revisions')
        ->assertOk()
        ->assertJsonCount(2, 'revisions')
        ->assertJsonPath('revisions.0.excerpt', 'Second draft.');
});

it('unpublishes a page back out of the reader tree', function () {
    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'temporary',
        'title' => 'Temporary',
        'content' => 'Live for now.',
    ])->assertCreated();
    $this->postJson('/docs/admin/api/pages/temporary/publish')->assertOk();
    $this->get('/docs/temporary')->assertOk();

    $this->postJson('/docs/admin/api/pages/temporary/unpublish')
        ->assertOk()
        ->assertJsonPath('published', false);

    $this->get('/docs/temporary')->assertNotFound();
});

it('rejects unknown front matter keys with a 422 that names them', function () {
    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'bad-fm',
        'title' => 'Bad FM',
        'content' => 'x',
        'front_matter' => ['description' => 'ok', 'bogus' => 1, 'nope' => 2],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('front_matter');

    expect(DocentPage::where('slug', 'bad-fm')->exists())->toBeFalse();
});

it('validates slug format on create', function (string $slug, bool $valid) {
    $response = $this->postJson('/docs/admin/api/pages', [
        'slug' => $slug,
        'title' => 'T',
        'content' => 'x',
    ]);

    $valid
        ? $response->assertCreated()
        : $response->assertStatus(422)->assertJsonValidationErrors('slug');
})->with([
    'spaces and caps' => ['Bad Slug', false],
    'leading underscore' => ['_private', false],
    'trailing slash' => ['foo/', false],
    'traversal' => ['../etc', false],
    'nested valid' => ['guides/new-topic', true],
    'partials namespace' => ['_partials/reusable', true],
]);

it('rejects a create that collides with an existing database page', function () {
    DocentPage::write('taken', 'x', ['title' => 'Taken']);

    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'taken',
        'title' => 'Taken Again',
        'content' => 'y',
    ])->assertStatus(422)->assertJsonValidationErrors('slug');
});

it('deletes a database page', function () {
    DocentPage::write('disposable', 'x', ['title' => 'Disposable'])->publish();

    $this->deleteJson('/docs/admin/api/pages/disposable')
        ->assertOk()
        ->assertJsonPath('deleted', true);

    $this->getJson('/docs/admin/api/pages/disposable')->assertNotFound();
    $this->get('/docs/disposable')->assertNotFound();
});

it('reverts to a past revision as a new draft', function () {
    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'history',
        'title' => 'History',
        'content' => 'Original content.',
    ])->assertCreated();
    $this->putJson('/docs/admin/api/pages/history', [
        'title' => 'History',
        'content' => 'Changed content.',
    ])->assertOk();

    $first = $this->getJson('/docs/admin/api/pages/history/revisions')
        ->json('revisions.1.id');

    $this->postJson("/docs/admin/api/pages/history/revert/{$first}")
        ->assertOk()
        ->assertJsonPath('content', 'Original content.');
});

it('404s a revert to a revision that belongs to a different page', function () {
    DocentPage::write('page-a', 'A', ['title' => 'A']);
    $other = DocentPage::write('page-b', 'B', ['title' => 'B']);
    $foreignRevision = $other->latestRevision()->id;

    $this->postJson("/docs/admin/api/pages/page-a/revert/{$foreignRevision}")
        ->assertNotFound();
});

it('overrides a filesystem page into a database draft matching the file', function () {
    $detail = $this->postJson('/docs/admin/api/pages/changelog/override')
        ->assertOk()
        ->assertJsonPath('store', 'database')
        ->assertJsonPath('front_matter.title', 'Changelog')
        ->json();

    expect($detail['content'])->toContain('# Changelog')
        ->and($detail['content'])->toContain('flibbertigibbet');

    // A second override collides with the freshly created database page.
    $this->postJson('/docs/admin/api/pages/changelog/override')->assertStatus(409);
});

it('404s an override of a slug that exists in no store', function () {
    $this->postJson('/docs/admin/api/pages/does-not-exist/override')->assertNotFound();
});

it('returns filesystem page detail as read-only', function () {
    $this->getJson('/docs/admin/api/pages/changelog')
        ->assertOk()
        ->assertJsonPath('store', 'filesystem')
        ->assertJsonPath('readonly', true)
        ->assertJsonPath('front_matter.title', 'Changelog');
});

it('serves, overrides, and publishes the home page through the _home alias', function () {
    // The root index.md has the empty-string slug, which cannot travel as a
    // URL path segment — it goes over the wire as the reserved `_home` alias.
    $this->getJson('/docs/admin/api/pages/_home')
        ->assertOk()
        ->assertJsonPath('slug', '')
        ->assertJsonPath('store', 'filesystem')
        ->assertJsonPath('front_matter.title', 'Home');

    $this->postJson('/docs/admin/api/pages/_home/override')
        ->assertOk()
        ->assertJsonPath('slug', '')
        ->assertJsonPath('store', 'database');

    $this->putJson('/docs/admin/api/pages/_home', [
        'title' => 'Home',
        'content' => 'Edited home body.',
    ])->assertOk();

    $this->postJson('/docs/admin/api/pages/_home/publish')->assertOk();

    $this->get('/docs')->assertOk()->assertSee('Edited home body.');
});
