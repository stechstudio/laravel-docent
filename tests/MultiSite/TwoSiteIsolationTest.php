<?php

declare(strict_types=1);

it('serves each site its own corpus at its own prefix', function () {
    $this->get('/help')
        ->assertOk()
        ->assertSee('Home');

    $this->resetDocentScope();
    $this->get('/admin/docs')->assertRedirect();

    $this->actingAs($this->adminDocsEditor())
        ->get('/admin/docs')
        ->assertOk()
        ->assertSee('Admin Home');
});

it('never leaks one site\'s pages into the other\'s search', function () {
    $this->getJson('/help/_search?q=runbook')
        ->assertOk()
        ->assertJsonCount(0, 'results');

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->getJson('/admin/docs/_search?q=runbook')
        ->assertOk()
        ->assertJsonPath('results.0.slug', 'internal/runbook');
});

it('registers keyed route names for both sites', function () {
    expect(route('docent.public.home', absolute: false))->toBe('/help')
        ->and(route('docent.admin.show', ['slug' => 'internal/runbook'], false))
        ->toBe('/admin/docs/internal/runbook');
});

it('gates each admin panel with its own site gate', function () {
    $this->actingAs($this->publicDocsEditor())
        ->get('/help/admin')
        ->assertOk();

    $this->resetDocentScope();
    $this->actingAs($this->publicDocsEditor())
        ->get('/admin/docs/admin')
        ->assertForbidden();

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->get('/admin/docs/admin')
        ->assertOk();

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->get('/help/admin')
        ->assertForbidden();
});

it('keeps llms.txt corpora separate', function () {
    $this->get('/help/llms.txt')
        ->assertOk()
        ->assertDontSee('Runbook');

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->get('/admin/docs/llms.txt')
        ->assertOk()
        ->assertSee('Runbook');
});

it('keeps each sitemap within its own site and prefix', function () {
    $public = $this->get('/help/sitemap.xml')->assertOk()->getContent();

    expect($public)
        ->toContain('<loc>http://localhost/help</loc>')
        ->toContain('<loc>http://localhost/help/guides/setup</loc>')
        ->not->toContain('admin/docs')
        ->not->toContain('internal/runbook');

    $this->resetDocentScope();
    $admin = $this->actingAs($this->adminDocsEditor())
        ->get('/admin/docs/sitemap.xml')
        ->assertOk()
        ->getContent();

    expect($admin)
        ->toContain('<loc>http://localhost/admin/docs</loc>')
        ->toContain('<loc>http://localhost/admin/docs/internal/runbook</loc>')
        ->not->toContain('/help/');
});

it('brands each site from its own theme override', function () {
    $this->get('/help')
        ->assertOk()
        ->assertSee('--docent-accent:#0284c7;', false)
        ->assertDontSee('--docent-accent:#e11d48;', false);

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->get('/admin/docs')
        ->assertOk()
        ->assertSee('--docent-accent:#e11d48;', false)
        ->assertDontSee('--docent-accent:#0284c7;', false);
});
