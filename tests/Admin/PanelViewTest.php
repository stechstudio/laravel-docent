<?php

it('renders the panel shell with mount point, assets, and csrf token', function () {
    $response = $this->actingAs($this->adminUser())->get('/docs/_admin');

    $response->assertOk()
        ->assertSee('id="docent-admin"', false)
        ->assertSee('docent-admin.css', false)
        ->assertSee('docent-admin.js', false)
        ->assertSee('name="csrf-token"', false);
});

it('serves the admin asset bundles with correct content types', function () {
    $this->get('/docs/_assets/docent-admin.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

    $this->get('/docs/_assets/docent-admin.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/javascript; charset=UTF-8');
});
