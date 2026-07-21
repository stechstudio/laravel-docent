<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Admin\Editor;
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
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/'.$fixture);
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
        'search-keywords',
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

it('emits structured json with --format=json', function () {
    [$exit, $output] = check('broken-docs', ['--format' => 'json']);

    $data = json_decode($output, true);

    expect($data)->toBeArray()
        ->and($data['ok'])->toBeFalse()
        ->and($data['errors'])->toBeGreaterThan(0)
        ->and($data['issues'])->toBeArray()
        ->and($data['issues'][0])->toHaveKeys(['check', 'severity', 'slug', 'message']);
    expect($exit)->toBe(1);
});

it('emits valid json for a clean tree', function () {
    [$exit, $output] = check('clean-docs', ['--format' => 'json']);
    $data = json_decode($output, true);

    expect($data['ok'])->toBeTrue()
        ->and($data['issues'])->toBe([]);
    expect($exit)->toBe(0);
});

it('silences a rule via config override', function () {
    config()->set('docent.check.rules', ['broken-link' => 'off']);
    [, $output] = check('broken-docs', ['--format' => 'json']);
    $data = json_decode($output, true);

    $checks = array_column($data['issues'], 'check');
    expect($checks)->not->toContain('broken-link');
});

it('demotes an error rule to a warning via config override', function () {
    config()->set('docent.check.rules', ['broken-link' => 'warning']);
    [, $output] = check('broken-docs', ['--format' => 'json']);
    $data = json_decode($output, true);

    foreach ($data['issues'] as $issue) {
        if ($issue['check'] === 'broken-link') {
            expect($issue['severity'])->toBe('warning');
        }
    }
});

it('does not run opt-in quality rules by default', function () {
    [, $output] = check('quality-docs', ['--format' => 'json']);
    $checks = array_column(json_decode($output, true)['issues'], 'check');

    expect($checks)->not->toContain('single-h1')
        ->and($checks)->not->toContain('description-length');
});

it('runs an opt-in rule only when enabled in config', function () {
    config()->set('docent.check.rules', ['single-h1' => 'warning', 'description-length' => 'warning']);
    [, $output] = check('quality-docs', ['--format' => 'json']);
    $checks = array_column(json_decode($output, true)['issues'], 'check');

    expect($checks)->toContain('single-h1')
        ->and($checks)->toContain('description-length');
});

it('can promote an opt-in rule to an error', function () {
    config()->set('docent.check.rules', ['single-h1' => 'error']);
    [$exit, $output] = check('quality-docs', ['--format' => 'json']);
    $issues = json_decode($output, true)['issues'];

    $single = array_values(array_filter($issues, fn ($i) => $i['check'] === 'single-h1'));
    expect($single[0]['severity'])->toBe('error');
    expect($exit)->toBe(1);
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
    config()->set('docent.sites.docs.navigation.links', [
        ['label' => 'Missing page', 'page' => 'does-not-exist', 'icon' => 'not-a-real-icon'],
        ['label' => 'Missing route', 'route' => 'does.not.exist'],
        ['label' => 'Ambiguous', 'url' => 'https://example.com', 'page' => 'index'],
    ]);
    config()->set('docent.sites.docs.navigation.topbar', [
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

it('reports unsafe and inconsistent redirect definitions', function () {
    [$exit, $output] = check('redirect-check-docs');

    expect($exit)->toBe(1)
        ->and($output)->toContain('redirect-missing')
        ->and($output)->toContain('redirect-external')
        ->and($output)->toContain('redirect-self')
        ->and($output)->toContain('redirect-cycle')
        ->and($output)->toContain('redirect-chain')
        ->and($output)->toContain('redirect-authorization')
        ->and($output)->toContain('redirect-reserved')
        ->and($output)->toContain('redirect-collision')
        ->and($output)->toContain('cycle-a -> cycle-b -> cycle-a')
        ->and($output)->toContain('chain -> narrow -> destination');
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

    :::video
    :::

    :::video https://example.com/watch/123
    :::

    ::::code-group
    ::::

    ::::code-group
    This is not a fenced code block.
    ::::
    MD);

    $checks = array_column(app(Editor::class)->draftIssues('components', $document), 'check');

    expect($checks)->toContain(
        'orphan-step',
        'orphan-tab',
        'empty-steps',
        'empty-tabs',
        'frame-without-image',
        'video-missing-source',
        'video-unrecognized-source',
        'empty-code-group',
        'invalid-code-group',
    );
});
