<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AppLinkKind;
use STS\Docent\Documents\Ast\ComponentNode;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Renderer\NodeText;

require_once __DIR__.'/Helpers.php';

it('parses dynamic value tokens with and without args', function () {
    $doc = docParse('Plan {{ value:account.plan_name }} and {{ value:usage.count seats monthly }}.');

    $values = docFindAll($doc, DynamicValue::class);

    expect($values[0]->key)->toBe('account.plan_name')
        ->and($values[0]->arguments)->toBe([])
        ->and($values[1]->key)->toBe('usage.count')
        ->and($values[1]->arguments)->toBe(['seats', 'monthly']);
});

it('parses link and route tokens', function () {
    $doc = docParse('See {{ link:billing.settings }} or {{ route:dashboard }}.');

    $links = docFindAll($doc, AppLink::class);

    expect($links[0]->kind)->toBe(AppLinkKind::Link)
        ->and($links[0]->key)->toBe('billing.settings')
        ->and($links[1]->kind)->toBe(AppLinkKind::Route)
        ->and($links[1]->key)->toBe('dashboard');
});

it('parses a token used as a markdown link destination', function () {
    $doc = docParse('[Billing]({{ link:billing.settings }})');

    $link = docFind($doc, Link::class);

    expect($link)->not->toBeNull()
        ->and($link->destination)->toBeInstanceOf(AppLink::class)
        ->and($link->destination->kind)->toBe(AppLinkKind::Link)
        ->and($link->destination->key)->toBe('billing.settings')
        ->and(trim(NodeText::extract($link)))->toBe('Billing');
});

it('parses a block-level component tag into a ComponentNode', function () {
    $doc = docParse('<docs-component name="billing-usage" plan="pro" />');

    $component = docFind($doc, ComponentNode::class);

    expect($component)->not->toBeNull()
        ->and($component->name)->toBe('billing-usage')
        ->and($component->attributes)->toBe(['plan' => 'pro']);
});

it('carries a source line onto inline dynamic values', function () {
    $doc = docParse("Line one.\n\nHere is {{ value:x }} on line three.");

    expect(docFind($doc, DynamicValue::class)->line)->toBe(3);
});
