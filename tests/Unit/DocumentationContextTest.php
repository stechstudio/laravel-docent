<?php

declare(strict_types=1);

use STS\Docent\Runtime\DocumentationContext;

require_once __DIR__.'/Helpers.php';

it('delegates can() to the injected gate closure', function () {
    $context = new DocumentationContext(
        parameters: ['team' => 7],
        gate: fn (string $ability, array $arguments) => $ability === 'billing.manage' && $arguments === ['invoice'],
    );

    expect($context->can('billing.manage', ['invoice']))->toBeTrue()
        ->and($context->can('billing.manage'))->toBeFalse()
        ->and($context->can('other'))->toBeFalse()
        ->and($context->parameter('team'))->toBe(7);
});

it('denies everything when no gate is provided', function () {
    $context = new DocumentationContext;

    expect($context->can('anything'))->toBeFalse();
});
