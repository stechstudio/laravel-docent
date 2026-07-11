<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\Repositories\DocumentationRepository;

/**
 * Point the repository at a fixture tree and run the check command, returning
 * [exitCode, output].
 *
 * @return array{0: int, 1: string}
 */
function check(string $fixture, array $parameters = []): array
{
    config()->set('docent.filesystem.path', dirname(__DIR__).'/fixtures/'.$fixture);
    app()->forgetInstance(DocumentationRepository::class);

    $exit = Artisan::call('docent:check', $parameters);

    return [$exit, Artisan::output()];
}

it('reports errors and exits non-zero on a broken tree', function () {
    [$exit, $output] = check('broken-docs');

    expect($exit)->toBe(1);
});

it('fires every check at least once on the broken fixtures', function () {
    [, $output] = check('broken-docs');

    $checks = [
        'front-matter',
        'missing-title',
        'duplicate-slug',
        'broken-link',
        'unknown-condition',
        'unknown-value',
        'unknown-link',
        'unknown-route',
        'unknown-component',
        'unknown-audience',
        'unknown-ability',
        'missing-include',
        'include-cycle',
        'missing-image',
        'heading-hierarchy',
    ];

    foreach ($checks as $check) {
        expect($output)->toContain($check);
    }
});

it('groups problems by page and shows source line numbers', function () {
    [, $output] = check('broken-docs');

    // slug:line references for issues that carry a line.
    expect($output)
        ->toContain('references:13')   // broken-link
        ->toContain('references:9')    // heading jump
        ->toContain('bad-yaml:4')      // malformed YAML
        ->toContain('cycles:7');       // include cycle
});

it('summarizes error and warning counts', function () {
    [, $output] = check('broken-docs');

    expect($output)
        ->toContain('Found')
        ->toContain('error')
        ->toContain('warning');
});

it('surfaces the include cycle path', function () {
    [, $output] = check('broken-docs');

    expect($output)->toContain('ping → pong → ping');
});

it('exits zero with a friendly message on a clean tree', function () {
    [$exit, $output] = check('clean-docs');

    expect($exit)->toBe(0);
    expect($output)->toContain('no problems found');
});

it('does not fail on warnings unless strict', function () {
    [$lenient] = check('warn-docs');
    expect($lenient)->toBe(0);

    [$strict, $output] = check('warn-docs', ['--strict' => true]);
    expect($strict)->toBe(1);
    expect($output)->toContain('missing-title');
});
