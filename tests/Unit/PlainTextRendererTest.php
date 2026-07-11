<?php

declare(strict_types=1);

use STS\Docent\Documents\Renderer\PlainTextRenderer;

require_once __DIR__.'/Helpers.php';

it('renders only content the context may see', function () {
    $doc = docParse(<<<'MD'
    Intro.

    :::can ability="billing.manage"
    Manage content.
    :::
    MD);

    $allowed = new PlainTextRenderer(docRegistry(), docContext(gate: fn () => true));
    $denied = new PlainTextRenderer(docRegistry(), docContext(gate: fn () => false));

    expect($allowed->render($doc))->toContain('Manage content.')
        ->and($denied->render($doc))->not->toContain('Manage content.');
});

it('resolves dynamic values to plain text', function () {
    $registry = docRegistry();
    $registry->value('plan', fn () => 'Pro');

    $doc = docParse('You are on {{ value:plan }}.');

    $text = (new PlainTextRenderer($registry, docContext()))->render($doc);

    expect($text)->toBe('You are on Pro.');
});
