<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;

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

it('flags suggestions that point at nonexistent pages', function () {
    app(DocentManager::class)->suggest('billing.*', ['missing-page']);

    $this->artisan('docent:check')
        ->expectsOutputToContain('missing-page')
        ->assertFailed();
});

it('warns about nested and empty promoted sections', function () {
    [$exit, $output] = check('section-warn-docs');

    expect($exit)->toBe(0)
        ->and($output)->toContain('invalid-section-depth')
        ->and($output)->toContain('empty-section');
});

it('validates persistent navigation link targets and icons', function () {
    config()->set('docent.navigation.links', [
        ['label' => 'Missing page', 'page' => 'does-not-exist', 'icon' => 'not-a-real-icon'],
        ['label' => 'Missing route', 'route' => 'does.not.exist'],
        ['label' => 'Ambiguous', 'url' => 'https://example.com', 'page' => 'index'],
    ]);
    config()->set('docent.navigation.topbar', [
        ['label' => 'Bad repo link', 'page' => 'also-does-not-exist'],
    ]);

    $this->artisan('docent:check')
        ->expectsOutputToContain('unknown-navigation-page')
        ->expectsOutputToContain('unknown-navigation-route')
        ->expectsOutputToContain('invalid-navigation-link')
        ->expectsOutputToContain('unknown-icon')
        ->expectsOutputToContain('navigation.topbar.0')
        ->assertFailed();
});

it('reports invalid and empty content component structures in draft checks', function () {
    $document = (new MarkdownDocumentParser)->parse(<<<'MD'
    :::step Orphaned
    Body.
    :::

    :::tab Orphaned
    Body.
    :::

    :::steps
    :::

    :::tabs
    :::

    :::frame caption="No screenshot"
    Text only.
    :::
    MD);

    $checks = array_column(app(DocentManager::class)->draftIssues('components', $document), 'check');

    expect($checks)->toContain('orphan-step', 'orphan-tab', 'empty-steps', 'empty-tabs', 'frame-without-image');
});
