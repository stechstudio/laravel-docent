<?php

it('does not mount the share credential when the feature is off', function () {
    $middleware = collect(app('router')->getRoutes()->getByName('docent.docs.show')->gatherMiddleware());

    expect($middleware->contains(fn ($m) => str_contains((string) $m, 'ShareCredential')))->toBeFalse();
});

it('leaves a would-be share link at the login wall', function () {
    // Minted the way an enabled site would, then presented to a site that
    // never turned the feature on.
    $this->get('/docs/guides/setup?s=fyeQ7mPv9LeKd2')->assertRedirect('/login');
});

it('reports itself as disabled', function () {
    expect($this->docent()->sharing()->enabled())->toBeFalse();
});

it('refuses to mint a share link for anyone', function () {
    expect($this->docent()->sharing()->canShare($this->adminUser()))->toBeFalse();
});
