<?php

use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Tests\Support\PlanUsageComponent;

it('falls back to the parent registry on a local miss', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => 'Global Plan');

    $site = new IntegrationRegistry(parent: $global);

    expect($site->hasValue('plan'))->toBeTrue()
        ->and($site->resolveValue('plan', new DocumentationContext))->toBe('Global Plan');
});

it('prefers a site-scoped registration over the global one', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => 'Global Plan');

    $site = new IntegrationRegistry(parent: $global);
    $site->value('plan', fn () => 'Admin Plan');

    expect($site->resolveValue('plan', new DocumentationContext))->toBe('Admin Plan');
});

it('falls back for every integration kind', function () {
    $context = new DocumentationContext;
    $component = new PlanUsageComponent;
    $global = new IntegrationRegistry;
    $global->condition('enabled', fn () => true);
    $global->link('settings', fn () => '/settings');
    $global->component('usage', $component);
    $global->audience('internal', fn () => true);

    $site = new IntegrationRegistry(parent: $global);

    expect($site->hasCondition('enabled'))->toBeTrue()
        ->and($site->resolveCondition('enabled', $context))->toBeTrue()
        ->and($site->hasLink('settings'))->toBeTrue()
        ->and($site->resolveLink('settings', $context))->toBe('/settings')
        ->and($site->hasComponent('usage'))->toBeTrue()
        ->and($site->resolveComponent('usage'))->toBe($component)
        ->and($site->hasAudience('internal'))->toBeTrue()
        ->and($site->resolveAudience('internal', $context))->toBeTrue();
});

it('merges suggestions from both layers, local last, capped at five', function () {
    $global = new IntegrationRegistry;
    $global->suggest('billing.*', ['billing/overview']);

    $site = new IntegrationRegistry(parent: $global);
    $site->suggest('billing.*', ['billing/admin', 'billing/overview']);

    expect($site->suggestionsFor('billing.index'))->toBe(['billing/overview', 'billing/admin']);
});

it('describes merged metadata with local winning by name', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => '', 'Global label');
    $global->value('seats', fn () => '', 'Seats');

    $site = new IntegrationRegistry(parent: $global);
    $site->value('plan', fn () => '', 'Site label');

    $values = collect($site->describe()['values'])->keyBy('name');

    expect($values['plan']['label'])->toBe('Site label')
        ->and($values['seats']['label'])->toBe('Seats');
});

it('does not inherit a parent label when a local registration omits one', function () {
    $global = new IntegrationRegistry;
    $global->value('plan', fn () => '', 'Global label');

    $site = new IntegrationRegistry(parent: $global);
    $site->value('plan', fn () => '');

    expect($site->valueLabel('plan'))->toBe('plan');
});
