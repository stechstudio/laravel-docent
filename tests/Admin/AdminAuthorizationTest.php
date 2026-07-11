<?php

use STS\Docent\Content\Models\DocentPage;

/**
 * Every admin route — the panel and its whole JSON API — is guarded by the
 * configured gate. Guests and users who fail the gate get 403; the account
 * owner gets through.
 */
dataset('adminRoutes', [
    'panel' => ['GET', '/docs/_admin'],
    'tree' => ['GET', '/docs/_admin/api/tree'],
    'meta' => ['GET', '/docs/_admin/api/meta'],
    'preview' => ['POST', '/docs/_admin/api/preview'],
    'uploads' => ['POST', '/docs/_admin/api/uploads'],
    'create' => ['POST', '/docs/_admin/api/pages'],
    'detail' => ['GET', '/docs/_admin/api/pages/some-slug'],
    'update' => ['PUT', '/docs/_admin/api/pages/some-slug'],
    'delete' => ['DELETE', '/docs/_admin/api/pages/some-slug'],
    'revisions' => ['GET', '/docs/_admin/api/pages/some-slug/revisions'],
    'publish' => ['POST', '/docs/_admin/api/pages/some-slug/publish'],
    'unpublish' => ['POST', '/docs/_admin/api/pages/some-slug/unpublish'],
    'revert' => ['POST', '/docs/_admin/api/pages/some-slug/revert/1'],
    'override' => ['POST', '/docs/_admin/api/pages/some-slug/override'],
]);

it('forbids guests on every admin route', function (string $method, string $uri) {
    $this->call($method, $uri)->assertForbidden();
})->with('adminRoutes');

it('forbids users who fail the gate on every admin route', function (string $method, string $uri) {
    $this->actingAs($this->memberUser())->call($method, $uri)->assertForbidden();
})->with('adminRoutes');

it('reaches the panel shell for a user who passes the gate', function () {
    $this->actingAs($this->adminUser())
        ->get('/docs/_admin')
        ->assertOk()
        ->assertSee('Admin')
        ->assertSee('id="docent-admin"', false);
});

it('reaches the tree for a user who passes the gate', function () {
    DocentPage::write('draft-page', '# Draft', ['title' => 'Draft Page']);

    $this->actingAs($this->adminUser())
        ->getJson('/docs/_admin/api/tree')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'draft-page', 'store' => 'database']);
});
