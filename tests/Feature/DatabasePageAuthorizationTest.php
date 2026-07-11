<?php

use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;

beforeEach(function () {
    config()->set('docent.database.enabled', true);
    app()->forgetInstance(DocumentationRepository::class);
    app()->forgetInstance(DocentManager::class);
    app()->forgetInstance(NavigationBuilder::class);
    app()->forgetInstance(SearchIndexer::class);
    app()->forgetInstance(SearchEngine::class);
});

it('gates a database page through its front matter authorize over http', function () {
    DocentPage::write('db-secret', "# Classified\n\nAdmins only.", ['title' => 'DB Secret', 'authorize' => 'reports.view'])->publish();

    $this->get('/docs/db-secret')->assertNotFound();

    $this->actingAs($this->adminUser())
        ->get('/docs/db-secret')
        ->assertOk()
        ->assertSee('Classified');
});

it('renders the database page title from the title column', function () {
    DocentPage::write('db-titled', 'Body only, no heading.', ['title' => 'Proper Title'])->publish();

    $this->get('/docs/db-titled')
        ->assertOk()
        ->assertSee('Proper Title');
});
