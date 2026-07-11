<?php

use STS\Docent\Content\Models\DocentPage;

beforeEach(function () {
    $this->actingAs($this->adminUser());
});

it('lists directories with effective group meta and provenance', function () {
    $groups = collect($this->getJson('/docs/admin/api/groups')->assertOk()->json('groups'))
        ->keyBy('directory');

    // billing/_group.yml provides label + order (no override yet).
    expect($groups['billing'])->toMatchArray([
        'directory' => 'billing',
        'label' => 'Billing',
        'order' => 2,
        'source' => 'file',
    ]);

    // guides/_group.yml also carries an icon.
    expect($groups['guides']['icon'])->toBe('book')
        ->and($groups['guides']['source'])->toBe('file');
});

it('overrides a group label and order, reflected in the reader sidebar, then restores on delete', function () {
    // Baseline: Guides (order 1) sorts before Billing (order 2).
    $this->get('/docs')->assertOk()->assertSeeInOrder(['Guides', 'Billing']);

    $this->putJson('/docs/admin/api/groups/billing', [
        'label' => 'Payments',
        'order' => 0,
        'icon' => 'credit-card',
    ])->assertOk()
        ->assertJsonFragment([
            'directory' => 'billing',
            'label' => 'Payments',
            'order' => 0,
            'icon' => 'credit-card',
            'source' => 'database',
        ]);

    // The database override wins and re-sorts the reader sidebar immediately.
    $this->get('/docs')->assertOk()
        ->assertSee('Payments')
        ->assertSeeInOrder(['Payments', 'Guides']);

    // Delete restores the _group.yml values.
    $this->deleteJson('/docs/admin/api/groups/billing')->assertOk()
        ->assertJsonFragment(['directory' => 'billing', 'label' => 'Billing', 'source' => 'file']);

    $this->get('/docs')->assertOk()
        ->assertSeeInOrder(['Guides', 'Billing'])
        ->assertDontSee('Payments');
});

it('renders a group icon inline in the reader sidebar', function () {
    // guides/_group.yml sets icon: book — the nav renders it before the label.
    $this->get('/docs')->assertOk()->assertSee('[&_svg]:h-4', false);
});

it('404s an update for a directory that has no pages', function () {
    $this->putJson('/docs/admin/api/groups/nonexistent', ['label' => 'X'])->assertNotFound();
});

it('422s an unknown icon', function () {
    $this->putJson('/docs/admin/api/groups/billing', ['label' => 'Billing', 'icon' => 'not-a-real-icon'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('icon');
});

it('accepts a heroicon name as a group icon', function () {
    $this->putJson('/docs/admin/api/groups/billing', ['label' => 'Billing', 'icon' => 'academic-cap'])
        ->assertOk()
        ->assertJsonFragment(['directory' => 'billing', 'icon' => 'academic-cap']);
});

it('requires a label on update', function () {
    $this->putJson('/docs/admin/api/groups/billing', ['order' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('label');
});

it('404s a delete when there is no database override', function () {
    $this->deleteJson('/docs/admin/api/groups/billing')->assertNotFound();
});

it('never exposes _groups rows in the admin tree or reader navigation', function () {
    DocentPage::write('_groups/billing', '', ['label' => 'Payments'])->publish();

    $slugs = collect($this->getJson('/docs/admin/api/tree')->assertOk()->json('pages'))->pluck('slug');
    expect($slugs)->not->toContain('_groups/billing');

    // Not reachable as a reader page, and never leaked into the sidebar markup.
    $this->get('/docs/_groups/billing')->assertNotFound();
    $this->get('/docs')->assertOk()->assertDontSee('_groups');
});
