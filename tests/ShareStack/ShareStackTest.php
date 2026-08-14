<?php

use Illuminate\Support\Facades\RateLimiter;

it('still runs an authorization middleware sitting below the guard', function () {
    // The reported failure mode: running the matched action from the
    // middleware skipped `can:` along with `auth`, so a share link reached a
    // page the host had gated on something other than being signed in.
    config()->set('docent_test.docs_readable', false);

    $this->get($this->shareUrl('guides/setup'))->assertForbidden();
});

it('serves the shared page once that same middleware allows it', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertHeader('X-Host-Middleware', 'ran');
});

it('turns away a second guess and says when to come back', function () {
    $url = $this->shareUrl('guides/setup');
    $tampered = substr($url, 0, -1).'x';

    // The limit is one attempt per minute here, so the first guess is spent
    // and the second is refused outright.
    $this->get($tampered)->assertRedirect('/login');

    $response = $this->get($tampered);

    $response->assertStatus(429);

    expect($response->headers->get('Retry-After'))->toBeNumeric();
});

it('does not spend the budget on links that actually work', function () {
    $url = $this->shareUrl('guides/setup');

    // A shared page pulls its stylesheet and every image through this same
    // middleware, so charging for success would rate-limit ordinary reading.
    foreach (range(1, 5) as $ignored) {
        $this->get($url)->assertOk();
    }

    expect(RateLimiter::attempts('docent-share|docs|127.0.0.1'))->toBe(0);
});

it('does not spend the budget of a signed-in reader whose link went stale', function () {
    $stale = substr($this->shareUrl('guides/setup'), 0, -1).'x';

    $this->actingAs($this->adminUser())->get($stale)->assertOk();

    expect(RateLimiter::attempts('docent-share|docs|127.0.0.1'))->toBe(0);
});
