<?php

use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Repositories\CompositeRepository;
use STS\Docent\Content\Repositories\DatabaseRepository;
use STS\Docent\Content\Repositories\FilesystemRepository;

beforeEach(function () {
    $this->repo = new DatabaseRepository;
});

it('serves only published pages', function () {
    DocentPage::write('draft', 'secret draft'); // never published
    DocentPage::write('live', '# Live')->publish();

    expect($this->repo->find('draft'))->toBeNull()
        ->and($this->repo->find('live'))->not->toBeNull();

    $slugs = array_map(fn ($ref) => $ref->slug, [...$this->repo->all()]);

    expect($slugs)->toBe(['live']);
});

it('keeps serving the published revision while newer edits stay drafts', function () {
    $page = DocentPage::write('x', '# v1')->publish();
    DocentPage::write('x', '# v2 draft');

    expect($this->repo->find('x')->rawContent)->toContain('# v1');

    $page->fresh()->publish();

    expect($this->repo->find('x')->rawContent)->toContain('# v2 draft');
});

it('exposes source provenance, format, hash, and base dir', function () {
    $page = DocentPage::write('billing/overview', '# Billing', format: 'markdown')->publish();
    $source = $this->repo->find('billing/overview');

    expect($source->path)->toBe('database:docent_pages/'.$page->getKey())
        ->and($source->format)->toBe('markdown')
        ->and($source->origin)->toBe('database')
        // The hash covers the composed document (front matter + content) so
        // metadata changes invalidate the AST cache too.
        ->and($source->hash)->toBe(sha1($source->rawContent))
        ->and($source->baseDir)->toBe('billing')
        ->and($source->lastModified)->toBeGreaterThan(0);
});

it('resolves partials by name and hides them from page enumeration', function () {
    DocentPage::write('_partials/legal', 'Reusable legal note')->publish();

    expect($this->repo->partial('legal')?->rawContent)->toContain('Reusable legal note')
        ->and($this->repo->find('_partials/legal'))->toBeNull();

    $slugs = array_map(fn ($ref) => $ref->slug, [...$this->repo->all()]);

    expect($slugs)->not->toContain('_partials/legal');
});

it('builds page references from the published front matter', function () {
    DocentPage::write('guides/deep', 'body', [
        'title' => 'Deep Guide',
        'description' => 'A deep guide.',
        'order' => 7,
        'hidden' => false,
        'authorize' => 'reports.view',
        'search' => ['exclude' => true, 'keywords' => ['private ledger']],
    ])->publish();

    [$ref] = [...$this->repo->all()];

    expect($ref->title)->toBe('Deep Guide')
        ->and($ref->description)->toBe('A deep guide.')
        ->and($ref->order)->toBe(7)
        ->and($ref->authorize)->toBe('reports.view')
        ->and($ref->searchExcluded)->toBeTrue()
        ->and($ref->searchKeywords)->toBe(['private ledger'])
        ->and($ref->directory)->toBe('guides');
});

it('reads group metadata from a reserved _groups row front matter, without publishing', function () {
    // Never published — group meta takes effect immediately.
    DocentPage::write('_groups/billing', '', ['label' => 'Invoices', 'order' => 5, 'icon' => 'credit-card']);

    expect($this->repo->groupMeta('billing'))->toBe(['label' => 'Invoices', 'order' => 5, 'icon' => 'credit-card'])
        ->and($this->repo->groupMeta('nonexistent'))->toBeNull();
});

it('lets a database group override beat a filesystem _group.yml through the composite', function () {
    $composite = new CompositeRepository(
        new DatabaseRepository,
        new FilesystemRepository(config('docent.filesystem.path')),
    );

    // billing/_group.yml → label Billing, order 2.
    expect($composite->groupMeta('billing'))->toBe(['label' => 'Billing', 'order' => 2]);

    DocentPage::write('_groups/billing', '', ['label' => 'Payments', 'order' => 9]);

    // Database wins the cascade.
    expect($composite->groupMeta('billing'))->toBe(['label' => 'Payments', 'order' => 9]);
});

it('changes the directory hash whenever served content changes', function () {
    // The hash keys nav/search/AST caches, so it must change whenever the set
    // of VISIBLE pages (or their content) changes. States that serve identical
    // content (unpublished-after-write vs never-published, deleted vs empty)
    // may legitimately share a hash.
    $hash0 = $this->repo->directoryHash();

    $page = DocentPage::write('x', 'v1');
    $page->publish();
    $afterPublish = $this->repo->directoryHash();

    expect($afterPublish)->not->toBe($hash0);

    DocentPage::write('x', 'v2');
    $page->fresh()->publish();
    $afterRepublish = $this->repo->directoryHash();

    expect($afterRepublish)->not->toBe($afterPublish);

    $page->fresh()->unpublish();
    $afterUnpublish = $this->repo->directoryHash();

    expect($afterUnpublish)->not->toBe($afterRepublish);

    $page->fresh()->publish();
    $republished = $this->repo->directoryHash();

    $page->fresh()->delete();

    expect($this->repo->directoryHash())->not->toBe($republished);
});
