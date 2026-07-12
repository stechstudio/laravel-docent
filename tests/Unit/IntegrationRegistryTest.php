<?php

declare(strict_types=1);

use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

require_once __DIR__.'/Helpers.php';

it('resolves closure registrations and passes token arguments', function () {
    $registry = new IntegrationRegistry;
    $registry->condition('on', fn (DocumentationContext $ctx) => true);
    $registry->value('greet', fn (DocumentationContext $ctx, string $name = 'x') => "Hi $name");
    $registry->link('home', fn (DocumentationContext $ctx) => '/home');
    $registry->audience('admins', fn (DocumentationContext $ctx) => false);

    $ctx = docContext();

    expect($registry->resolveCondition('on', $ctx))->toBeTrue()
        ->and($registry->resolveValue('greet', $ctx, ['Sam']))->toBe('Hi Sam')
        ->and($registry->resolveLink('home', $ctx))->toBe('/home')
        ->and($registry->resolveAudience('admins', $ctx))->toBeFalse();
});

it('returns null for unregistered identifiers', function () {
    $registry = new IntegrationRegistry;
    $ctx = docContext();

    expect($registry->resolveCondition('nope', $ctx))->toBeNull()
        ->and($registry->resolveValue('nope', $ctx))->toBeNull()
        ->and($registry->resolveLink('nope', $ctx))->toBeNull()
        ->and($registry->resolveAudience('nope', $ctx))->toBeNull()
        ->and($registry->resolveComponent('nope'))->toBeNull();
});

it('resolves class-string components through the injectable class resolver', function () {
    $resolved = [];
    $registry = new IntegrationRegistry(function (string $class) use (&$resolved) {
        $resolved[] = $class;

        return new $class;
    });

    $registry->component('widget', RegistryTestComponent::class);

    $component = $registry->resolveComponent('widget');

    expect($component)->toBeInstanceOf(DocumentationComponent::class)
        ->and($component->render(docContext(), ['v' => '1']))->toBe('widget:1')
        ->and($resolved)->toBe([RegistryTestComponent::class]);
});

it('reports registration existence', function () {
    $registry = new IntegrationRegistry;
    $registry->condition('c', fn () => true);

    expect($registry->hasCondition('c'))->toBeTrue()
        ->and($registry->hasCondition('missing'))->toBeFalse();
});

it('matches and deduplicates contextual suggestion patterns in registration order', function () {
    $registry = new IntegrationRegistry;
    $registry->suggest('billing.*', ['billing/overview', 'billing/payment-methods'])
        ->suggest('*.settings', ['billing/payment-methods', 'account/profile']);

    expect($registry->suggestionsFor('billing.settings'))->toBe([
        'billing/overview',
        'billing/payment-methods',
        'account/profile',
    ])->and($registry->suggestionsFor('reports.index'))->toBe([]);
});

it('caps merged suggestions at five', function () {
    $registry = new IntegrationRegistry;
    $registry->suggest('a.*', ['one', 'two', 'three'])
        ->suggest('a.b', ['four', 'five', 'six', 'seven']);

    expect($registry->suggestionsFor('a.b'))->toBe(['one', 'two', 'three', 'four', 'five']);
});

it('rejects empty suggestion patterns and slugs', function () {
    expect(fn () => (new IntegrationRegistry)->suggest('', ['welcome']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new IntegrationRegistry)->suggest('*', ['']))
        ->toThrow(InvalidArgumentException::class);
});

final class RegistryTestComponent implements DocumentationComponent
{
    public function render(DocumentationContext $context, array $attributes): string
    {
        return 'widget:'.$attributes['v'];
    }
}
