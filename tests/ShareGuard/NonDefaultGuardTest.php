<?php

use Illuminate\Support\Facades\Auth;

it('gives a reader signed in on the route guard their own page', function () {
    // Deliberately not actingAs(): that also calls shouldUse(), which makes
    // `admin` the default guard and hides the very thing under test. Signing
    // in on one guard while the default stays `web` is the real shape, and
    // the reported failure — the credential read them as anonymous and
    // stripped a page they were entitled to see in full.
    Auth::guard('admin')->setUser($this->adminUser());

    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertSee('docent-topbar', escape: false)
        ->assertSee('Team Plan')
        ->assertSee('You can manage billing.');
});

it('still shares the page with a visitor signed in nowhere', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertDontSee('docent-topbar', escape: false)
        ->assertSee('{Account plan}');
});

it('still turns away a visitor with no token', function () {
    $this->get('/docs/guides/setup')->assertRedirect('/login');
});

it('does not mistake the default guard for the route guard', function () {
    // Signed in on `web`, which this route does not consult, so the guard
    // would reject them — the token is what gets them the page, and it gets
    // them the shared one.
    $this->actingAs($this->adminUser(), 'web')
        ->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertDontSee('docent-topbar', escape: false);
});
