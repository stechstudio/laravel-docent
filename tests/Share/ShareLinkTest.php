<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;

it('serves a shared page to a visitor who is not signed in', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertSee('Install the thing before you continue.');
});

it('sends a visitor without a token to the login wall', function () {
    $this->get('/docs/guides/setup')->assertRedirect('/login');
});

it('sends a visitor with a tampered token to the login wall', function () {
    $url = $this->shareUrl('guides/setup');

    $this->get(substr($url, 0, -1).'x')->assertRedirect('/login');
});

it('refuses a token minted for a different page', function () {
    $token = (string) parse_url($this->shareUrl('guides/setup'), PHP_URL_QUERY);

    $this->get('/docs/billing/overview?'.$token)->assertRedirect('/login');
});

it('refuses a token once its day has passed', function () {
    $url = $this->shareUrl('guides/setup', days: 2);

    $this->travelTo(Date::now()->addDays(3));

    $this->get($url)->assertRedirect('/login');
});

it('still serves the page on its final day', function () {
    $url = $this->shareUrl('guides/setup', days: 2);

    $this->travelTo(Date::now()->addDays(2));

    $this->get($url)->assertOk();
});

it('gives a signed-in reader their own page rather than the shared copy', function () {
    $response = $this->actingAs($this->adminUser())->get($this->shareUrl('guides/setup'));

    $response->assertOk()
        // The full reader shell, not the stripped share view.
        ->assertSee('docent-topbar', escape: false)
        // Resolved against *their* abilities, not held back.
        ->assertSee('You can manage billing.')
        ->assertSee('Team Plan');
});

it('leaves the reader shell out of a shared page', function () {
    $response = $this->get($this->shareUrl('guides/setup'));

    $response->assertOk()
        ->assertDontSee('docent-topbar', escape: false)
        ->assertDontSee('docent-sidebar', escape: false)
        ->assertDontSee('data-docent-topbar-search', escape: false)
        ->assertDontSee('docent-assistant', escape: false);
});

it('keeps a shared page out of search engines', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('name="robots" content="noindex, nofollow"', escape: false);
});

it('offers the visitor a way to sign in for everything else', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertSee('You are reading a single shared page.')
        ->assertSee('Sign in to read the full documentation');
});

it('points the sign-in offer at a configured url when one is set', function () {
    config()->set('docent.share.login_url', 'https://accounts.example.test/login');

    $this->get($this->shareUrl('guides/setup'))
        ->assertSee('https://accounts.example.test/login', escape: false);
});

it('records a shared read against its own surface', function () {
    config()->set('docent.insights.enabled', true);

    $this->get($this->shareUrl('guides/setup'))->assertOk();

    $this->assertDatabaseHas('docent_insight_events', [
        'page_slug' => 'guides/setup',
        'surface' => 'share',
    ]);
})->uses(RefreshDatabase::class);
