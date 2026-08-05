<?php

it('serves each site only the images in its own content directory', function () {
    $this->actingAs($this->adminUser());

    $this->get('/admin/docs/_images/images/internal.png')->assertOk();
    $this->get('/help/_images/public-only.png')->assertOk();
});

it('never serves one site the other site content directory', function () {
    $this->actingAs($this->adminUser());

    // Each site's root is its own filesystem.path, so the other's files simply
    // are not addressable — no shared root to escape from.
    $this->get('/help/_images/images/internal.png')->assertNotFound();
    $this->get('/admin/docs/_images/public-only.png')->assertNotFound();
});

it('keeps a private site images behind its own middleware', function () {
    // The admin site's route group carries `auth`, so its images do too.
    $this->get('/admin/docs/_images/images/internal.png')->assertRedirect('/login');
});
