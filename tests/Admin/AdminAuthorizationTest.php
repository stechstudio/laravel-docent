<?php

use STS\Docent\Content\Models\DocentPage;

/**
 * Every admin route — the panel and its whole JSON API — is guarded by the
 * configured gate. Guests and users who fail the gate get 403; the account
 * owner gets through.
 */
dataset('adminRoutes', [
    'panel' => ['GET', '/docs/admin'],
    'tree' => ['GET', '/docs/admin/api/tree'],
    'meta' => ['GET', '/docs/admin/api/meta'],
    'icons' => ['GET', '/docs/admin/api/icons'],
    'groups' => ['GET', '/docs/admin/api/groups'],
    'group-update' => ['PUT', '/docs/admin/api/groups/guides'],
    'group-delete' => ['DELETE', '/docs/admin/api/groups/guides'],
    'preview' => ['POST', '/docs/admin/api/preview'],
    'uploads' => ['POST', '/docs/admin/api/uploads'],
    'create' => ['POST', '/docs/admin/api/pages'],
    'detail' => ['GET', '/docs/admin/api/pages/some-slug'],
    'update' => ['PUT', '/docs/admin/api/pages/some-slug'],
    'delete' => ['DELETE', '/docs/admin/api/pages/some-slug'],
    'revisions' => ['GET', '/docs/admin/api/pages/some-slug/revisions'],
    'publish' => ['POST', '/docs/admin/api/pages/some-slug/publish'],
    'unpublish' => ['POST', '/docs/admin/api/pages/some-slug/unpublish'],
    'revert' => ['POST', '/docs/admin/api/pages/some-slug/revert/1'],
    'override' => ['POST', '/docs/admin/api/pages/some-slug/override'],
]);

it('forbids guests on every admin route', function (string $method, string $uri) {
    $this->call($method, $uri)->assertForbidden();
})->with('adminRoutes');

it('forbids users who fail the gate on every admin route', function (string $method, string $uri) {
    $this->actingAs($this->memberUser())->call($method, $uri)->assertForbidden();
})->with('adminRoutes');

it('reaches the panel shell for a user who passes the gate', function () {
    $this->actingAs($this->adminUser())
        ->get('/docs/admin')
        ->assertOk()
        ->assertSee('Admin')
        ->assertSee('id="docent-admin"', false);
});

it('reaches the tree for a user who passes the gate', function () {
    DocentPage::write('draft-page', '# Draft', ['title' => 'Draft Page']);

    $this->actingAs($this->adminUser())
        ->getJson('/docs/admin/api/tree')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'draft-page', 'store' => 'database']);
});

it('serves the icon library to a user who passes the gate', function () {
    $response = $this->actingAs($this->adminUser())
        ->getJson('/docs/admin/api/icons')
        ->assertOk();

    $icons = $response->json('icons');

    expect($icons)->toBeArray()->not->toBeEmpty();

    $first = $icons[0];
    expect($first)->toHaveKeys(['name', 'svg'])
        ->and($first['svg'])->toContain('<svg');
});
