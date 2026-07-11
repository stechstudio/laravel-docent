<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\AuthorizationMode;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\CalloutType;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Renderer\NodeText;

require_once __DIR__.'/Helpers.php';

it('parses can and cannot authorization blocks', function () {
    $doc = docParse(<<<'MD'
    :::can ability="billing.manage"
    Yes.
    :::

    :::cannot ability="billing.manage"
    No.
    :::
    MD);

    $blocks = docFindAll($doc, AuthorizationBlock::class);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]->mode)->toBe(AuthorizationMode::Can)
        ->and($blocks[0]->ability)->toBe('billing.manage')
        ->and($blocks[1]->mode)->toBe(AuthorizationMode::Cannot);
});

it('parses when and unless conditions including bare shorthand', function () {
    $doc = docParse(<<<'MD'
    :::when advanced-exports
    Shorthand.
    :::

    :::unless condition="beta"
    Attr form.
    :::
    MD);

    $blocks = docFindAll($doc, ConditionBlock::class);

    expect($blocks[0]->condition)->toBe('advanced-exports')
        ->and($blocks[0]->negated)->toBeFalse()
        ->and($blocks[1]->condition)->toBe('beta')
        ->and($blocks[1]->negated)->toBeTrue();
});

it('parses audience blocks', function () {
    $doc = docParse(<<<'MD'
    :::audience name="billing-admin"
    Admins only.
    :::
    MD);

    expect(docFind($doc, AudienceBlock::class)->audience)->toBe('billing-admin');
});

it('parses every callout type with optional title', function () {
    $doc = docParse(<<<'MD'
    :::note title="Heads up"
    A note.
    :::

    :::danger
    Careful.
    :::
    MD);

    $callouts = docFindAll($doc, Callout::class);

    expect($callouts[0]->type)->toBe(CalloutType::Note)
        ->and($callouts[0]->title)->toBe('Heads up')
        ->and($callouts[1]->type)->toBe(CalloutType::Danger)
        ->and($callouts[1]->title)->toBeNull();
});

it('nests directives and closes them at the correct depth', function () {
    $doc = docParse(<<<'MD'
    :::can ability="a"
    Outer.

    :::when inner-cond
    Inner.
    :::

    After inner, still outer.
    :::
    MD);

    $auth = docFind($doc, AuthorizationBlock::class);
    $cond = docFind($auth, ConditionBlock::class);

    expect($auth)->not->toBeNull()
        ->and($cond)->not->toBeNull()
        ->and($cond->condition)->toBe('inner-cond');

    // The outer block still owns the "After inner" paragraph.
    $paragraphs = docFindAll($auth, Paragraph::class);
    $texts = array_map(fn ($p) => trim(NodeText::extract($p)), $paragraphs);

    expect($texts)->toContain('Outer.')
        ->and($texts)->toContain('After inner, still outer.');
});

it('treats include as self-contained with an optional closing fence', function () {
    $doc = docParse(<<<'MD'
    :::include name="permissions-note"
    :::

    Following paragraph.
    MD);

    $include = docFind($doc, IncludeNode::class);

    expect($include->name)->toBe('permissions-note')
        ->and($include->children)->toBe([]);

    // The following paragraph is a sibling, not swallowed by the include.
    $paragraphs = docFindAll($doc, Paragraph::class);
    expect($paragraphs)->toHaveCount(1)
        ->and(trim(NodeText::extract($paragraphs[0])))->toBe('Following paragraph.');
});

it('parses directive attributes and markdown inside directive bodies', function () {
    $doc = docParse(<<<'MD'
    :::note
    Text with **bold** inside.
    :::
    MD);

    $callout = docFind($doc, Callout::class);
    $strong = docFind($callout, Strong::class);

    expect($strong)->not->toBeNull();
});

it('records source line numbers on directive blocks', function () {
    $doc = docParse("Intro.\n\n:::note\nHi.\n:::");

    expect(docFind($doc, Callout::class)->line)->toBe(3);
});
