<?php

use Illuminate\Support\Facades\Route;

/**
 * With the admin panel disabled (the default), none of its routes exist and the
 * panel path falls through to the ordinary page resolver (a 404).
 */
it('registers no admin routes when the panel is disabled', function () {
    expect(Route::has('docent.docs.admin'))->toBeFalse()
        ->and(Route::has('docent.docs.admin.tree'))->toBeFalse()
        ->and(Route::has('docent.docs.admin.pages.store'))->toBeFalse();

    $this->get('/docs/admin')->assertNotFound();
    $this->getJson('/docs/admin/api/tree')->assertNotFound();
});
