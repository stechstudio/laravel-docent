<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\Repositories\DocumentationRepository;

/**
 * @return array<int, array<string, mixed>>
 */
function gatedLinkIssues(array $rules = ['gated-link' => 'warning']): array
{
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/gated-link-docs');
    config()->set('docent.check.rules', $rules);
    app()->forgetInstance(DocumentationRepository::class);

    Artisan::call('docent:check', ['--format' => 'json']);

    return array_values(array_filter(
        json_decode(Artisan::output(), true)['issues'],
        fn (array $i): bool => $i['check'] === 'gated-link',
    ));
}

it('does not run unless the rule is enabled', function () {
    expect(gatedLinkIssues(rules: []))->toBeEmpty();
});

it('flags an ungated page linking to an ability-gated page', function () {
    expect(implode("\n", array_column(gatedLinkIssues(), 'message')))
        ->toContain('"billing"')
        ->toContain('billing.manage');
});

it('flags an ungated page linking to an audience-gated page', function () {
    expect(implode("\n", array_column(gatedLinkIssues(), 'message')))
        ->toContain('"internal-notes"')
        ->toContain('internal');
});

it('stays silent about links to ungated pages', function () {
    expect(implode("\n", array_column(gatedLinkIssues(), 'message')))
        ->not->toContain('"open"');
});

it('says nothing when the source page is itself gated', function () {
    // reports.view and billing.manage are different abilities, not a lattice —
    // whether they overlap is not statically decidable, so this is not reported.
    expect(array_column(gatedLinkIssues(), 'slug'))->not->toContain('from-gated');
});

it('treats a link inside a :::can block as already gated', function () {
    // Line 15 sits inside :::can ability="billing.manage".
    expect(array_column(gatedLinkIssues(), 'line'))->not->toContain(15);
});

it('treats a link inside an :::audience block as already gated', function () {
    // Line 19 sits inside :::audience name="internal".
    expect(array_column(gatedLinkIssues(), 'line'))->not->toContain(19);
});

it('still flags a link inside a :::cannot block, which widens rather than narrows', function () {
    // Line 23 sits inside :::cannot — its readers are those who FAIL the gate.
    expect(array_column(gatedLinkIssues(), 'line'))->toContain(23);
});

it('reports exactly the three ungated-to-gated links', function () {
    expect(gatedLinkIssues())->toHaveCount(3);
});

it('is a warning by default and can be promoted to an error', function () {
    expect(array_column(gatedLinkIssues(), 'severity'))->each->toBe('warning');

    expect(array_column(gatedLinkIssues(['gated-link' => 'error']), 'severity'))
        ->each->toBe('error');
});
