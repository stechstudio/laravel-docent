<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\Heading;

require_once __DIR__.'/Helpers.php';

it('parses typed front matter accessors', function () {
    $doc = docParse(<<<'MD'
    ---
    title: Payment Methods
    description: Manage your cards
    authorize: billing.view
    audience: billing-admin
    order: 3
    hidden: true
    search:
      exclude: true
    redirect: billing/old
    ---

    # Body
    MD);

    $fm = $doc->frontMatter();

    expect($fm->title())->toBe('Payment Methods')
        ->and($fm->description())->toBe('Manage your cards')
        ->and($fm->authorize())->toBe('billing.view')
        ->and($fm->audience())->toBe('billing-admin')
        ->and($fm->order())->toBe(3)
        ->and($fm->hidden())->toBeTrue()
        ->and($fm->searchExcluded())->toBeTrue()
        ->and($fm->redirect())->toBe('billing/old')
        ->and($fm->get('title'))->toBe('Payment Methods')
        ->and($fm->all())->toHaveKey('title');
});

it('defaults sensibly when front matter is absent', function () {
    $doc = docParse("# No front matter\n\nBody.");

    $fm = $doc->frontMatter();

    expect($fm->title())->toBeNull()
        ->and($fm->order())->toBeNull()
        ->and($fm->hidden())->toBeFalse()
        ->and($fm->searchExcluded())->toBeFalse()
        ->and($fm->all())->toBe([]);
});

it('offsets source line numbers past the front matter', function () {
    $doc = docParse(<<<'MD'
    ---
    title: Offset
    ---

    # Heading
    MD);

    $heading = docFind($doc, Heading::class);

    // Heading sits on line 5 of the original source.
    expect($heading->line)->toBe(5);
});
