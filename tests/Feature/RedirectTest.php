<?php

use STS\Docent\Ai\AiRetriever;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Navigation\NavigationItem;
use STS\Docent\Search\SearchEngine;
use STS\Docent\Search\SearchIndexer;

beforeEach(function () {
    config()->set('docent.filesystem.path', dirname(__DIR__).'/fixtures/redirect-docs');

    foreach ([DocumentationRepository::class, DocentManager::class, NavigationBuilder::class, SearchIndexer::class, SearchEngine::class, AiRetriever::class] as $service) {
        app()->forgetInstance($service);
    }
});

it('redirects reader and agent URLs permanently while preserving the query string', function () {
    $this->get('/docs/old-setup?utm_source=saved-link&view=compact')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/docs/guides/setup?utm_source=saved-link&view=compact');

    $this->get('/docs/old-setup.md?source=agent')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/docs/guides/setup.md?source=agent');
});

it('authorizes the final destination before revealing a redirect', function () {
    $this->get('/docs/old-secret')->assertNotFound();

    $this->actingAs($this->adminUser())
        ->get('/docs/old-secret')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/docs/billing/secret');
});

it('follows short chains to the final page and rejects chains beyond the bound', function () {
    $this->get('/docs/older-setup')
        ->assertStatus(301)
        ->assertRedirect('http://localhost/docs/guides/setup');

    $this->get('/docs/hop-one')->assertNotFound();
});

it('keeps redirect stubs out of navigation, search, agent output, and assistant retrieval', function () {
    $manager = app(DocentManager::class);
    $context = $this->contextFor(null);
    $navigation = $manager->navigation($context);
    $slugs = [];

    $collect = function (array $nodes) use (&$collect, &$slugs): void {
        foreach ($nodes as $node) {
            if ($node instanceof NavigationItem) {
                $slugs[] = $node->slug;
            } elseif ($node instanceof NavigationGroup) {
                $collect([...$node->items, ...$node->groups]);
            }
        }
    };
    $collect($navigation);

    $records = app(SearchIndexer::class)->records();
    $recordSlugs = array_map(static fn ($record): string => $record->slug, $records);
    $retrieval = app(AiRetriever::class)->retrieve(
        'Where is the retired workflow marker?',
        $context,
        currentSlug: 'old-setup',
    );

    expect($slugs)->not->toContain('old-setup', 'older-setup', 'old-secret')
        ->and($recordSlugs)->not->toContain('old-setup', 'older-setup', 'old-secret')
        ->and(array_map(static fn ($candidate): string => $candidate->record->slug, $retrieval->candidates))->not->toContain('old-setup')
        ->and($manager->llmsText($context))->not->toContain('Retired Setup')
        ->and($manager->llmsFullText($context))->not->toContain('Retired workflow marker');
});

it('uses the real page when its slug collides with a redirect stub', function () {
    config()->set('docent.filesystem.path', dirname(__DIR__).'/fixtures/redirect-check-docs');
    app()->forgetInstance(DocumentationRepository::class);
    app()->forgetInstance(DocentManager::class);

    $this->get('/docs/collision')
        ->assertOk()
        ->assertSee('Real page wins');
});
