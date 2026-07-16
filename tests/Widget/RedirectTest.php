<?php

use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;

beforeEach(function () {
    config()->set('docent.filesystem.path', dirname(__DIR__).'/fixtures/redirect-docs');

    foreach ([DocumentationRepository::class, DocentManager::class, NavigationBuilder::class, SearchIndexer::class, SearchEngine::class] as $service) {
        app()->forgetInstance($service);
    }
});

it('keeps redirects and suggestions inside widget space without surfacing stubs', function () {
    $this->get('/docs/_widget/old-setup?source=panel')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/docs/_widget/guides/setup?source=panel');

    $this->getJson('/docs/_widget/_suggestions?slugs[]=old-setup&slugs[]=guides/setup')
        ->assertOk()
        ->assertJsonCount(1, 'suggestions')
        ->assertJsonPath('suggestions.0.slug', 'guides/setup');
});
