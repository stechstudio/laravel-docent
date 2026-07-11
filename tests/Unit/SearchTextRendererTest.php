<?php

declare(strict_types=1);

use STS\Docent\Documents\Renderer\SearchTextRenderer;

require_once __DIR__.'/Helpers.php';

it('never leaks conditional content into the search index', function () {
    $doc = docParse(<<<'MD'
    # Public Heading

    Public paragraph.

    :::can ability="secret.ability"
    TOP SECRET authorization content.
    :::

    :::when advanced-exports
    HIDDEN condition content.
    :::

    :::audience name="admins"
    HIDDEN audience content.
    :::
    MD);

    $text = (new SearchTextRenderer)->render($doc);

    expect($text)->toContain('Public Heading')
        ->and($text)->toContain('Public paragraph.')
        ->and($text)->not->toContain('TOP SECRET')
        ->and($text)->not->toContain('HIDDEN');
});

it('emits nothing for dynamic values', function () {
    $doc = docParse('Your plan is {{ value:account.plan_name }} today.');

    $text = (new SearchTextRenderer)->render($doc);

    expect($text)->toBe('Your plan is today.');
});

it('resolves includes but still skips conditional content within them', function () {
    $partial = docParse(<<<'MD'
    Static partial text.

    :::can ability="x"
    SECRET inside include.
    :::
    MD);

    $doc = docParse(':::include name="note"');

    $text = (new SearchTextRenderer(fn (string $name) => $name === 'note' ? $partial : null))->render($doc);

    expect($text)->toContain('Static partial text.')
        ->and($text)->not->toContain('SECRET');
});

it('includes code block text for searching', function () {
    $doc = docParse("```php\necho 'searchable';\n```");

    expect((new SearchTextRenderer)->render($doc))->toContain('searchable');
});
