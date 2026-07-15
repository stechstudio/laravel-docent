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

it('ranks database page search keywords without rendering them', function () {
    DocentPage::write('db-video', 'Use a frame to embed media.', [
        'title' => 'Media guide',
        'search' => ['keywords' => ['insert video']],
    ])->publish();

    $result = app(SearchEngine::class)->search('insert video', $this->contextFor(null))[0] ?? null;

    expect($result?->slug)->toBe('db-video')
        ->and($result?->snippet)->not->toContain('insert video');

    $this->get('/docs/db-video')->assertOk()->assertDontSee('insert video');
});

it('sanitizes raw html in published database markdown and database partials', function () {
    DocentPage::write('_partials/hostile', <<<'MD'
        <img src="x" onerror="window.partialExecuted = true">
        <script>window.partialExecuted = true</script>
        MD)->publish();

    DocentPage::write('database-html', <<<'MD'
        # Database HTML

        Ordinary Markdown survives.

        Ordinary <span class="safe-inline" onclick="window.inlineExecuted = true">inline HTML</span> survives too.

        <aside class="safe-html" aria-label="Safe block">Safe raw HTML</aside>
        <a href="javascript:alert(1)" onclick="window.clicked = true">Unsafe link</a>
        <script>window.pageExecuted = true</script>

        :::include name="hostile"
        MD)->publish();

    $this->get('/docs/database-html')
        ->assertOk()
        ->assertSee('Ordinary Markdown survives.')
        ->assertSee('<span class="safe-inline">inline HTML</span>', false)
        ->assertSee('<aside class="safe-html" aria-label="Safe block">Safe raw HTML</aside>', false)
        ->assertSee('Unsafe link')
        ->assertDontSee('javascript:', false)
        ->assertDontSee('onclick=', false)
        ->assertDontSee('onerror=', false)
        ->assertDontSee('inlineExecuted', false)
        ->assertDontSee('partialExecuted', false)
        ->assertDontSee('pageExecuted', false);
});

it('keeps reviewed repository html enabled by default', function () {
    $this->get('/docs/changelog')
        ->assertOk()
        ->assertSee('<aside data-reviewed-html="yes">Reviewed repository HTML.</aside>', false);
});

it('sanitizes database partials inside a repository page', function () {
    DocentPage::write('_partials/permissions-note', <<<'MD'
        <img src="x" onerror="window.partialExecuted = true">
        <script>window.partialExecuted = true</script>
        MD)->publish();

    $this->get('/docs/guides/setup')
        ->assertOk()
        ->assertSee('Install the thing')
        ->assertSee('<img src="x" />', false)
        ->assertDontSee('onerror=', false)
        ->assertDontSee('partialExecuted', false);
});
