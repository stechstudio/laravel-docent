<?php

require_once __DIR__.'/Helpers.php';

use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\CardGroup;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\SearchTextRenderer;

it('parses a cards group with nested cards using longer outer fences', function () {
    $doc = docParse(<<<'MD'
    ::::cards
    :::card title="Getting Started" icon="rocket" href="getting-started"
    Install and configure.
    :::
    :::card title="Billing" icon="credit-card" href="billing"
    Plans and invoices.
    :::
    ::::
    MD);

    $group = docFind($doc, CardGroup::class);
    $cards = docFindAll($doc, Card::class);

    expect($group)->not->toBeNull()
        ->and($group->columns)->toBe(2)
        ->and($cards)->toHaveCount(2)
        ->and($cards[0]->title)->toBe('Getting Started')
        ->and($cards[0]->icon)->toBe('rocket')
        ->and($cards[0]->href)->toBe('getting-started')
        ->and($cards[1]->title)->toBe('Billing');
});

it('honors the columns attribute', function () {
    $doc = docParse("::::cards columns=\"3\"\n:::card title=\"One\"\n:::\n::::");

    expect(docFind($doc, CardGroup::class)->columns)->toBe(3);
});

it('parses a standalone card outside a group', function () {
    $doc = docParse(":::card title=\"Solo\" href=\"guides\"\nBody text.\n:::");

    expect(docFind($doc, CardGroup::class))->toBeNull()
        ->and(docFind($doc, Card::class)->title)->toBe('Solo');
});

it('renders linked cards through the shared url resolution', function () {
    $doc = docParse(":::card title=\"Setup\" icon=\"rocket\" href=\"setup\"\nGet going.\n:::");

    $html = (new HtmlRenderer(
        docRegistry(),
        docContext(),
        options: ['base_dir' => 'guides', 'route_prefix' => 'docs'],
        urlResolver: fn (string $slug): string => '/docs/'.$slug,
    ))->render($doc);

    expect($html)->toContain('<a class="docent-card" href="/docs/guides/setup">')
        ->toContain('docent-card-icon')
        ->toContain('docent-card-title')
        ->toContain('Get going.');
});

it('renders a card without href as a static panel and skips unknown icons', function () {
    $doc = docParse(":::card title=\"Panel\" icon=\"not-a-real-icon\"\nJust info.\n:::");

    $html = (new HtmlRenderer(docRegistry(), docContext()))->render($doc);

    expect($html)->toContain('<div class="docent-card">')
        ->not->toContain('<a class="docent-card"')
        ->not->toContain('docent-card-icon');
});

it('hides cards inside a failed authorization block and shows them when granted', function () {
    $doc = docParse(<<<'MD'
    ::::cards
    :::can ability="reports.view"
    :::card title="Reports" href="reports"
    Admin only.
    :::
    :::
    ::::
    MD);

    $denied = (new HtmlRenderer(docRegistry(), docContext(gate: fn () => false)))->render($doc);
    $granted = (new HtmlRenderer(docRegistry(), docContext(gate: fn () => true)))->render($doc);

    expect($denied)->not->toContain('Reports')
        ->and($granted)->toContain('Reports');
});

it('indexes unconditional card text but never gated card text', function () {
    $doc = docParse(<<<'MD'
    ::::cards
    :::card title="Public Card"
    Visible body.
    :::
    :::can ability="reports.view"
    :::card title="SecretCard"
    Hidden body.
    :::
    :::
    ::::
    MD);

    $text = (new SearchTextRenderer)->render($doc);

    expect($text)->toContain('Public Card')
        ->toContain('Visible body.')
        ->not->toContain('SecretCard')
        ->not->toContain('Hidden body.');
});

it('exposes the layout front matter accessor with a docs default', function () {
    expect(docParse('Plain page.')->frontMatter()->layout())->toBe('docs')
        ->and(docParse("---\nlayout: landing\n---\n\nHero.")->frontMatter()->layout())->toBe('landing');
});
