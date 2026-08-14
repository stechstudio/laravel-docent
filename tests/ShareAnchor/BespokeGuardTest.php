<?php

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use STS\Docent\Http\Middleware\ShareCredential;
use STS\Docent\Tests\Support\RejectsGuests;

it('runs the credential ahead of a guard named through share.before', function () {
    $this->get($this->shareUrl('guides/setup'))
        ->assertOk()
        ->assertSee('Install the thing before you continue.');
});

it('still turns a visitor without a token away at that guard', function () {
    $this->get('/docs/guides/setup')->assertRedirect('/login');
});

it('seats the bespoke anchor in the priority map so the ordering can hold', function () {
    $priority = $this->app->make(HttpKernel::class)->getMiddlewarePriority();

    // Laravel appends rather than inserts when the anchor is absent, which
    // would put the credential behind the guard and fail silently.
    expect($priority)->toContain(RejectsGuests::class)
        ->and(array_search(ShareCredential::class, $priority, true))
        ->toBeLessThan(array_search(RejectsGuests::class, $priority, true));
});

it('sorts the credential first on the route itself', function () {
    $gathered = $this->app->make('router')->gatherRouteMiddleware(
        $this->app->make('router')->getRoutes()->getByName('docent.docs.show'),
    );

    $credential = array_search(ShareCredential::class.':docs', $gathered, true);
    $guard = array_search(RejectsGuests::class, $gathered, true);

    expect($credential)->toBeInt()->toBeLessThan($guard);
});
