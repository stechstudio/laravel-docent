<?php

declare(strict_types=1);

use STS\Docent\Facades\Docent;
use STS\Docent\Runtime\DocumentationContext;

beforeEach(function () {
    config()->set('docent.sites.admin', [
        'name' => 'Admin Docs',
        'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
        'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
    ]);
    $this->resetDocentScope();
});

it('exposes the current site on every integration closure context', function () {
    $seen = [];
    Docent::value('site.probe', function (DocumentationContext $context) use (&$seen): string {
        $seen[] = $context->site?->key;

        return $context->site?->name ?? '';
    });

    $docs = Docent::site('docs');
    $admin = Docent::site('admin');

    expect($docs->registry()->resolveValue('site.probe', $docs->contextFor(null)))->toBe('Fixture Docs')
        ->and($admin->registry()->resolveValue('site.probe', $admin->contextFor(null)))->toBe('Admin Docs')
        ->and($seen)->toBe(['docs', 'admin']);
});

it('prefers a site-scoped closure over the global one end to end', function () {
    Docent::value('plan', fn () => 'Global Plan');
    Docent::site('admin')->value('plan', fn () => 'Admin Plan');

    $docs = Docent::site('docs');
    $admin = Docent::site('admin');

    expect($docs->registry()->resolveValue('plan', $docs->contextFor(null)))->toBe('Global Plan')
        ->and($admin->registry()->resolveValue('plan', $admin->contextFor(null)))->toBe('Admin Plan');
});
