<?php

use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;

beforeEach(function () {
    $this->actingAs($this->adminUser());
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/redirect-docs');

    foreach ([DocumentationRepository::class, DocentManager::class, NavigationBuilder::class, SearchIndexer::class, SearchEngine::class] as $service) {
        app()->forgetInstance($service);
    }
});

it('shows repository redirect stubs read-only and blocks editable copies', function () {
    $this->getJson('/docs/admin/api/pages/old-setup')
        ->assertOk()
        ->assertJsonPath('readonly', true)
        ->assertJsonPath('locked', true)
        ->assertJsonPath('front_matter.redirect', 'guides/setup');

    $this->postJson('/docs/admin/api/pages/old-setup/override')->assertForbidden();
});

it('accepts redirect front matter on database-authored pages', function () {
    $this->postJson('/docs/admin/api/pages', [
        'slug' => 'database-alias',
        'title' => 'Database alias',
        'content' => 'This body is never rendered.',
        'front_matter' => ['redirect' => 'guides/setup'],
    ])->assertCreated()
        ->assertJsonPath('front_matter.redirect', 'guides/setup');

    $this->postJson('/docs/admin/api/pages/database-alias/publish')->assertOk();

    $this->get('/docs/database-alias')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/docs/guides/setup');
});
