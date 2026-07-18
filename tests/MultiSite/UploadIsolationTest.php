<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

it('never serves another site\'s uploads from a shared disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('docent/public/open.png', 'public-image');
    Storage::disk('public')->put('docent/admin/secret.png', 'admin-image');

    $this->get('/help/_uploads/docent/public/open.png')->assertOk();
    $this->get('/help/_uploads/docent/admin/secret.png')->assertNotFound();

    $this->resetDocentScope();
    $this->actingAs($this->adminDocsEditor())
        ->get('/admin/docs/_uploads/docent/admin/secret.png')
        ->assertOk();
});

it('does not serve legacy un-namespaced upload paths', function () {
    Storage::fake('public');
    Storage::disk('public')->put('docent/legacy.png', 'image');

    $this->get('/help/_uploads/docent/legacy.png')->assertNotFound();
});
