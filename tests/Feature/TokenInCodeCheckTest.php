<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\Repositories\DocumentationRepository;

/**
 * @return array{0: int, 1: array<int, array<string, mixed>>}
 */
function tokenCheck(array $parameters = []): array
{
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/token-code-docs');
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
    $found = tokenIssues($issues);

    expect(array_column($found, 'message'))
        ->toContain('Registered token "{{ value:account.plan }}" sits inside code and will render verbatim.');
});

it('flags a registered link trapped in an inline code span', function () {
    [, $issues] = tokenCheck();

    expect(array_column(tokenIssues($issues), 'message'))
        ->toContain('Registered token "{{ link:billing.settings }}" sits inside code and will render verbatim.');
});

it('flags a registered token inside a fenced code block', function () {
    [, $issues] = tokenCheck();
    $lines = array_column(array_filter(
        tokenIssues($issues),
        fn (array $i): bool => str_contains($i['message'], 'value:account.plan'),
    ), 'line');

    // Both the inline span (line 8) and the fenced block (line 12).
    expect($lines)->toContain(8)->toContain(12);
});

it('stays silent for unregistered keys used as generic examples', function () {
    [, $issues] = tokenCheck();
    $found = tokenIssues($issues);

    expect(array_column($found, 'slug'))->not->toContain('dialect');
});

it('stays silent for tokens outside code, which resolve normally', function () {
    [, $issues] = tokenCheck();
    $found = tokenIssues($issues);

    // Line 16 holds two resolvable tokens in ordinary prose.
    expect(array_column($found, 'line'))->not->toContain(16);
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
