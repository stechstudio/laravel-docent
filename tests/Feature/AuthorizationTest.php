<?php

use STS\Docent\Facades\Docent;

it('hides an authorized page from a guest with a 404 by default', function () {
    $this->get('/docs/billing/secret')->assertNotFound();
    $this->get('/docs/reports')->assertNotFound();
});

it('shows an authorized page to a permitted user', function () {
    $this->actingAs($this->adminUser());

    $this->get('/docs/billing/secret')->assertOk()->assertSee('Only billing admins');
    $this->get('/docs/reports')->assertOk()->assertSee('Admin-only reports');
});

it('denies a non-permitted authenticated user', function () {
    $this->actingAs($this->memberUser());

    $this->get('/docs/billing/secret')->assertNotFound();
});

it('can return a 403 for denied pages when configured', function () {
    config()->set('docent.authorization.denied_response', 403);

    $this->get('/docs/billing/secret')->assertForbidden();
});

it('can redirect for denied pages when configured', function () {
    config()->set('docent.authorization.denied_response', 'redirect:/login');

    $this->get('/docs/billing/secret')->assertRedirect('/login');
});

it('authorizes at the page level via the Page affordance', function () {
    $page = Docent::page('billing/secret');

    expect($page->authorize($this->contextFor($this->adminUser())))->toBeTrue()
        ->and($page->authorize($this->contextFor($this->memberUser())))->toBeFalse()
        ->and($page->authorize($this->contextFor(null)))->toBeFalse();
});
