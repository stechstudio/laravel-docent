<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;

it('targets the requested site', function () {
    $html = Blade::render('<x-docent::widget site="admin" />');

    expect($html)
        ->toContain('admin\/docs\/_widget')
        ->toContain('admin\/docs\/_widget\/_suggestions');
});

it('defaults to the configured default site', function () {
    $html = Blade::render('<x-docent::widget />');

    expect($html)
        ->toContain('docs\/_widget')
        ->not->toContain('admin\/docs\/_widget');
});

it('rejects an unknown site key', function () {
    try {
        Blade::render('<x-docent::widget site="missing" />');
    } catch (ViewException $exception) {
        $root = $exception;

        while ($root->getPrevious() !== null) {
            $root = $root->getPrevious();
        }

        expect($root)
            ->toBeInstanceOf(InvalidArgumentException::class)
            ->and($root->getMessage())->toBe('Unknown Docent site [missing].');

        return;
    }

    $this->fail('Rendering a widget for an unknown site should fail.');
});
