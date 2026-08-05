<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Admin\Editor;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;

beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/token-code-docs');
});

/**
 * @return array{0: int, 1: array<int, array<string, mixed>>}
 */
function tokenCheck(array $parameters = []): array
{
    app()->forgetInstance(DocumentationRepository::class);

    $exit = Artisan::call('docent:check', $parameters + ['--format' => 'json']);

    return [$exit, json_decode(Artisan::output(), true)['issues']];
}

/**
 * @return array<int, array<string, mixed>>
 */
function tokenIssues(array $issues): array
{
    return array_values(array_filter($issues, fn (array $i): bool => $i['check'] === 'token-in-code'));
}

it('flags a registered value trapped in an inline code span', function () {
    [, $issues] = tokenCheck();

    expect(array_column(tokenIssues($issues), 'message'))
        ->toContain('Registered token "{{ value:account.plan }}" sits inside a code span and will render verbatim.');
});

it('flags a registered link trapped in an inline code span', function () {
    [, $issues] = tokenCheck();

    expect(array_column(tokenIssues($issues), 'message'))
        ->toContain('Registered token "{{ link:billing.settings }}" sits inside a code span and will render verbatim.');
});

it('keeps a token argument in the message so occurrences stay distinguishable', function () {
    [, $issues] = tokenCheck();

    expect(array_column(tokenIssues($issues), 'message'))
        ->toContain('Registered token "{{ value:account.plan monthly }}" sits inside a code span and will render verbatim.');
});

it('leaves fenced code blocks alone, since an example is meant to be literal', function () {
    // The fixture holds a fenced block containing a registered value on line 15.
    [, $issues] = tokenCheck();

    expect(array_column(tokenIssues($issues), 'line'))->not->toContain(15);
});

it('stays silent for unregistered keys used as generic examples', function () {
    [, $issues] = tokenCheck();

    expect(array_column(tokenIssues($issues), 'slug'))->not->toContain('dialect');
});

it('stays silent for tokens outside code, which resolve normally', function () {
    [, $issues] = tokenCheck();

    // Line 18 holds two resolvable tokens in ordinary prose.
    expect(array_column(tokenIssues($issues), 'line'))->not->toContain(18);
});

it('reports exactly the three trapped inline spans', function () {
    [, $issues] = tokenCheck();

    expect(tokenIssues($issues))->toHaveCount(3);
});

it('is a warning, so it does not fail a run unless strict', function () {
    [$lenient, $issues] = tokenCheck();

    expect($lenient)->toBe(0)
        ->and(array_column(tokenIssues($issues), 'severity'))->each->toBe('warning');

    [$strict] = tokenCheck(['--strict' => true]);

    expect($strict)->toBe(1);
});

it('can be silenced entirely from config', function () {
    config()->set('docent.check.rules', ['token-in-code' => 'off']);
    [, $issues] = tokenCheck();

    expect(tokenIssues($issues))->toBeEmpty();
});

/**
 * @return array<int, array<string, mixed>>
 */
function draftTokenIssues(): array
{
    $draft = app(MarkdownDocumentParser::class)->parse(
        "---\ntitle: Draft\n---\n\nYour plan is `{{ value:account.plan }}`.\n",
    );

    return array_values(array_filter(
        app(Editor::class)->draftIssues('draft', $draft),
        fn (array $i): bool => $i['check'] === 'token-in-code',
    ));
}

it('reports the same problem on an admin draft', function () {
    expect(draftTokenIssues())->toHaveCount(1);
});

it('honors a silenced rule on an admin draft too', function () {
    // `check.rules` is applied wherever checks run, not only in the command —
    // otherwise the editor keeps nagging about a rule CI was told to ignore.
    config()->set('docent.check.rules', ['token-in-code' => 'off']);

    expect(draftTokenIssues())->toBeEmpty();
});

it('honors a promoted severity on an admin draft too', function () {
    config()->set('docent.check.rules', ['token-in-code' => 'error']);

    expect(array_column(draftTokenIssues(), 'severity'))->toBe(['error']);
});
