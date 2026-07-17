<?php

use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Sites\SiteRef;

it('exposes key and name', function () {
    $ref = new SiteRef('admin', 'Admin Docs');

    expect($ref->key)->toBe('admin')->and($ref->name)->toBe('Admin Docs');
});

it('rides on the documentation context and defaults to null', function () {
    expect((new DocumentationContext)->site)->toBeNull();

    $context = new DocumentationContext(site: new SiteRef('docs', 'Docs'));

    expect($context->site->key)->toBe('docs');
});
