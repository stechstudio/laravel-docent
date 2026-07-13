<?php

use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;

beforeEach(function () {
    config()->set('docent.filesystem.path', dirname(__DIR__).'/fixtures/locked-docs');
    app()->forgetInstance(DocumentationRepository::class);
    app()->forgetScopedInstances();
    $this->actingAs($this->adminUser());
});

it('resolves page and group locks as a one-way filesystem cascade', function () {
    $filesystem = new FilesystemRepository(config('docent.filesystem.path'));
    $references = collect($filesystem->all())->keyBy('slug');

    expect($filesystem->pageLocked('locked'))->toBeTrue()
        ->and($filesystem->pageLocked('open'))->toBeFalse()
        ->and($filesystem->pageLocked('policies/security'))->toBeTrue()
        ->and($filesystem->pageLocked('policies/terms'))->toBeTrue()
        ->and($references['policies/terms']->locked)->toBeTrue()
        ->and($filesystem->pageLocked('policies/db-only'))->toBeFalse()
        ->and($filesystem->partialLocked('legal'))->toBeTrue()
        ->and($filesystem->partialLocked('disclaimer'))->toBeTrue();
});

it('serves locked repository pages and partials over existing database shadows', function () {
    DocentPage::write('locked', 'Database policy', ['title' => 'Database Policy'])->publish();
    DocentPage::write('_partials/legal', 'Database legal text')->publish();

    $repository = app(DocumentationRepository::class);
    $locked = $repository->find('locked');
    $partial = $repository->partial('legal');
    $references = collect($repository->all())->keyBy('slug');

    expect($locked?->rawContent)->toContain('This content is maintained in git.')
        ->and($locked?->path)->toEndWith('locked-docs/locked.md')
        ->and($partial?->rawContent)->toContain('Repository legal text.')
        ->and($references['locked']->title)->toBe('Locked Policy')
        ->and($repository->shadowed())->not->toContain('locked')
        ->and($repository->lockedShadowed())->toContain('locked')
        ->and($repository->lockedShadowed())->toContain('_partials/legal');
});

it('blocks every admin mutation at a locked repository slug', function () {
    $page = DocentPage::write('locked', 'Old database copy', ['title' => 'Old Copy']);
    $page->publish();
    $revision = $page->latestRevision()->getKey();

    $message = "The repository page 'locked' is locked and cannot be changed in Docent admin.";

    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'locked',
        'title' => 'Replacement',
        'content' => 'Replacement',
    ])->assertForbidden()->assertJsonPath('message', $message);

    $this->putJson('/docs/admin/api/pages/locked', [
        'title' => 'Changed',
        'content' => 'Changed',
    ])->assertForbidden()->assertJsonPath('message', $message);

    $this->deleteJson('/docs/admin/api/pages/locked')->assertForbidden();
    $this->postJson('/docs/admin/api/pages/locked/publish')->assertForbidden();
    $this->postJson('/docs/admin/api/pages/locked/unpublish')->assertForbidden();
    $this->postJson("/docs/admin/api/pages/locked/revert/{$revision}")->assertForbidden();
    $this->postJson('/docs/admin/api/pages/locked/override')->assertForbidden();

    $this->postJson('/docs/admin/api/pages', [
        'slug' => '_partials/legal',
        'title' => 'Legal',
        'content' => 'Changed legal text',
    ])->assertForbidden();

    expect($page->fresh()->trashed())->toBeFalse();
});

it('shows locked pages as repository-owned read-only content in the admin', function () {
    DocentPage::write('locked', 'Ignored database copy', ['title' => 'Ignored Copy'])->publish();

    $this->getJson('/docs/admin/api/pages/locked')
        ->assertOk()
        ->assertJsonPath('store', 'filesystem')
        ->assertJsonPath('readonly', true)
        ->assertJsonPath('locked', true)
        ->assertJsonPath('title', 'Locked Policy');

    $pages = $this->getJson('/docs/admin/api/tree')->assertOk()->json('pages');
    $lockedEntries = collect($pages)->where('slug', 'locked');

    expect($lockedEntries)->toHaveCount(2)
        ->and($lockedEntries->every(fn (array $entry): bool => $entry['locked'] === true))->toBeTrue();
});

it('keeps ordinary file overrides and database-native pages inside locked groups working', function () {
    $this->postJson('/docs/admin/api/pages/open/override')
        ->assertOk()
        ->assertJsonPath('store', 'database')
        ->assertJsonPath('locked', false);

    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'policies/db-only',
        'title' => 'Database Only',
        'content' => 'Allowed because no repository page occupies this slug.',
    ])->assertCreated();
});

it('warns when a database row is ignored beneath a locked repository page', function () {
    // Even an unpublished draft is stale under a lock and should be surfaced.
    DocentPage::write('locked', 'Ignored database copy', ['title' => 'Ignored Copy']);
    DocentPage::write('_partials/legal', 'Ignored database legal text');

    $this->artisan('docent:check')
        ->expectsOutputToContain('locked-page-shadowed')
        ->expectsOutputToContain('_partials/legal')
        ->doesntExpectOutputToContain('shadowed-page')
        ->assertSuccessful();
});
