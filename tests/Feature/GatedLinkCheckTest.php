<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\Repositories\DocumentationRepository;

beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/gated-link-docs');
});

/**
 * Run the checker and return the raw `gated-link` issues.
 *
 * @return array<int, array<string, mixed>>
 */
function gatedLinkIssues(array $rules = ['gated-link' => 'warning']): array
{
    config()->set('docent.check.rules', $rules);
    app()->forgetInstance(DocumentationRepository::class);

    Artisan::call('docent:check', ['--format' => 'json']);

    return array_values(array_filter(
        json_decode(Artisan::output(), true)['issues'],
        fn (array $i): bool => $i['check'] === 'gated-link',
    ));
}

/**
 * The same issues as `slug: message` pairs, so assertions name the page and the
 * requirement rather than a fixture line number.
 *
 * @return array<int, string>
 */
function gatedLinks(array $rules = ['gated-link' => 'warning']): array
{
    return array_map(fn (array $i): string => $i['slug'].': '.$i['message'], gatedLinkIssues($rules));
}

function gatedLinkReport(array $rules = ['gated-link' => 'warning']): string
{
    return implode("\n", gatedLinks($rules));
}

it('does not run unless the rule is enabled', function () {
    expect(gatedLinks(rules: []))->toBeEmpty();
});

it('flags an ungated page linking to an ability-gated page', function () {
    expect(gatedLinkReport())->toContain('the ability "billing.manage"');
});

it('flags an ungated page linking to an audience-gated page', function () {
    expect(gatedLinkReport())->toContain('the audience "internal"');
});

it('stays silent about links to ungated pages', function () {
    expect(gatedLinkReport())->not->toContain('"open"');
});

it('reports exactly the links whose readers lack a guarantee', function () {
    // The whole contract in one place, against a fixture built for it. Absent
    // from this list, and deliberately so: roles.md line 15 (a :::can naming
    // billing.manage) and line 19 (an :::audience naming internal), which
    // declare exactly what their targets require.
    $located = array_map(
        fn (array $i): string => $i['slug'].':'.$i['line'],
        gatedLinkIssues(),
    );

    expect($located)->toBe([
        'from-gated:9',          // reports.view does not imply billing.manage
        'partial-guarantee:9',   // guarantees the ability, not the audience
        'roles:8',               // plain ungated link
        'roles:10',              // audience-gated target
        'roles:23',              // :::can naming a different ability
        'roles:27',              // :::cannot widens rather than narrows
        'roles:31',              // :::when is a condition, not authorization
        'roles:35',              // card href
        'roles:40',              // link inside an included partial
    ]);
});

it('still flags a block guaranteeing a different ability', function () {
    // :::can ability="reports.view" says nothing about billing.manage, and
    // Docent cannot know whether one implies the other.
    $report = gatedLinkReport();

    expect(substr_count($report, 'roles: Link to "billing"'))->toBe(5);
});

it('still flags a link inside a cannot block, which widens rather than narrows', function () {
    expect(gatedLinkReport())->toContain('roles: Link to "billing"');
});

it('accepts a block naming the target own requirement', function () {
    // Two links on roles.md sit under blocks declaring exactly what their
    // targets require, and neither appears in the located set above.
    expect(gatedLinkReport())->not->toContain('roles: Link to "open"');
});

it('compares requirements when the source page is itself gated', function () {
    // reports.view does not imply billing.manage, so this is still a dead end
    // for readers of the gated page.
    expect(gatedLinkReport())->toContain('from-gated: Link to "billing"');
});

it('accepts a gated page linking to a page with the same requirement', function () {
    expect(gatedLinkReport())->not->toContain('from-gated: Link to "reports"');
});

it('reports every requirement a target has, not just the first', function () {
    // `both` needs an ability AND an audience; guaranteeing only the ability
    // still leaves non-internal billing managers at a 404.
    expect(gatedLinkReport())
        ->toContain('partial-guarantee: Link to "both" requires the audience "internal"')
        ->not->toContain('partial-guarantee: Link to "both" requires the ability');
});

it('checks card hrefs', function () {
    expect(gatedLinkReport())->toContain('roles: Link to "billing"');
});

it('follows links into included partials', function () {
    // A partial is rendered into the page, so its dead ends are the page's.
    expect(gatedLinkReport())->toContain('Link in partial "billing-link" to "billing"');
});

it('tells the author what to do about it', function () {
    expect(gatedLinkReport())->toContain('Wrap it in a block naming that requirement');
});

it('is a warning by default and can be promoted to an error', function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/gated-link-docs');
    app()->forgetInstance(DocumentationRepository::class);
    Artisan::call('docent:check', ['--format' => 'json']);

    config()->set('docent.check.rules', ['gated-link' => 'warning']);
    Artisan::call('docent:check', ['--format' => 'json']);
    $warning = array_values(array_filter(
        json_decode(Artisan::output(), true)['issues'],
        fn (array $i): bool => $i['check'] === 'gated-link',
    ));

    expect(array_column($warning, 'severity'))->each->toBe('warning');

    config()->set('docent.check.rules', ['gated-link' => 'error']);
    Artisan::call('docent:check', ['--format' => 'json']);
    $error = array_values(array_filter(
        json_decode(Artisan::output(), true)['issues'],
        fn (array $i): bool => $i['check'] === 'gated-link',
    ));

    expect(array_column($error, 'severity'))->each->toBe('error');
});
