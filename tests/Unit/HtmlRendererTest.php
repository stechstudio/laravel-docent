<?php

declare(strict_types=1);

use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;

require_once __DIR__.'/Helpers.php';

function renderHtml(string $markdown, $registry, $context, array $options = [], ?Closure $include = null, ?Closure $url = null): string
{
    return (new HtmlRenderer($registry, $context, $options, $include, $url))->render(docParse($markdown));
}

it('renders headings with anchor slugs', function () {
    $html = renderHtml('## Set Up', docRegistry(), docContext());

    expect($html)->toBe('<h2 id="set-up">Set Up</h2>');
});

it('shows can blocks only when the gate allows', function () {
    $md = ":::can ability=\"billing.manage\"\nSecret.\n:::";

    $allowed = docContext(gate: fn ($ability) => true);
    $denied = docContext(gate: fn ($ability) => false);

    expect(renderHtml($md, docRegistry(), $allowed))->toContain('Secret.')
        ->and(renderHtml($md, docRegistry(), $denied))->not->toContain('Secret.');
});

it('negates visibility for cannot blocks', function () {
    $md = ":::cannot ability=\"billing.manage\"\nUpsell.\n:::";

    expect(renderHtml($md, docRegistry(), docContext(gate: fn () => false)))->toContain('Upsell.')
        ->and(renderHtml($md, docRegistry(), docContext(gate: fn () => true)))->not->toContain('Upsell.');
});

it('resolves when/unless conditions and hides unknown conditions', function () {
    $registry = docRegistry();
    $registry->condition('on', fn () => true);
    $registry->condition('off', fn () => false);

    expect(renderHtml(":::when on\nY.\n:::", $registry, docContext()))->toContain('Y.')
        ->and(renderHtml(":::when off\nN.\n:::", $registry, docContext()))->not->toContain('N.')
        ->and(renderHtml(":::unless off\nY.\n:::", $registry, docContext()))->toContain('Y.')
        // Unknown condition renders nothing.
        ->and(renderHtml(":::when ghost\nX.\n:::", $registry, docContext()))->not->toContain('X.');
});

it('gates audience blocks via registry and preview override', function () {
    $registry = docRegistry();
    $registry->audience('admins', fn () => true);
    $registry->audience('devs', fn () => false);

    expect(renderHtml(":::audience name=\"admins\"\nA.\n:::", $registry, docContext()))->toContain('A.')
        ->and(renderHtml(":::audience name=\"devs\"\nD.\n:::", $registry, docContext()))->not->toContain('D.')
        // Preview override forces a single audience perspective.
        ->and(renderHtml(":::audience name=\"devs\"\nD.\n:::", $registry, docContext(audience: 'devs')))->toContain('D.')
        ->and(renderHtml(":::audience name=\"admins\"\nA.\n:::", $registry, docContext(audience: 'devs')))->not->toContain('A.');
});

it('always escapes dynamic values', function () {
    $registry = docRegistry();
    $registry->value('x', fn () => '<script>alert(1)</script> & "q"');

    $html = renderHtml('Value {{ value:x }}.', $registry, docContext());

    expect($html)->toContain('&lt;script&gt;')
        ->and($html)->not->toContain('<script>');
});

it('escapes hostile text content', function () {
    $html = renderHtml('5 < 6 & 7 > 4', docRegistry(), docContext());

    expect($html)->toContain('5 &lt; 6 &amp; 7 &gt; 4')
        ->and($html)->not->toContain('< 6');
});

it('strips raw html when allow_html is false', function () {
    $md = "Inline <b>bold</b> here.\n\n<div>block</div>";

    expect(renderHtml($md, docRegistry(), docContext(), ['allow_html' => false]))->not->toContain('<b>')
        ->and(renderHtml($md, docRegistry(), docContext(), ['allow_html' => true]))->toContain('<b>');
});

it('resolves app links as href destinations', function () {
    $registry = docRegistry();
    $registry->link('billing.settings', fn () => '/app/billing');

    $html = renderHtml('[Billing]({{ link:billing.settings }})', $registry, docContext());

    expect($html)->toContain('<a href="/app/billing">Billing</a>');
});

it('resolves route app links via an injected route resolver', function () {
    $html = renderHtml(
        '{{ route:dashboard }}',
        docRegistry(),
        docContext(),
        ['route_resolver' => fn (string $name, array $params) => '/'.$name],
    );

    expect($html)->toContain('href="/dashboard"');
});

it('resolves slug-style internal links through the url resolver', function () {
    $html = renderHtml(
        '[Setup](getting-started/setup)',
        docRegistry(),
        docContext(),
        [],
        null,
        fn (string $slug) => '/docs/'.$slug,
    );

    expect($html)->toContain('<a href="/docs/getting-started/setup">Setup</a>');
});

it('strips javascript: scheme from markdown links but keeps the label', function () {
    $html = renderHtml('[click me](javascript:alert(document.cookie))', docRegistry(), docContext());

    expect($html)->toContain('click me')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('<a ');
});

it('allows safe schemes and relative links through', function () {
    expect(renderHtml('[x](https://example.com)', docRegistry(), docContext()))->toContain('href="https://example.com"')
        ->and(renderHtml('[x](mailto:a@b.com)', docRegistry(), docContext()))->toContain('href="mailto:a@b.com"');
});

it('downgrades a card with a javascript: href to a non-linked card', function () {
    $md = "::::cards\n:::card title=\"Bad\" href=\"javascript:alert(1)\"\nbody\n:::\n::::";
    $html = renderHtml($md, docRegistry(), docContext());

    expect($html)->toContain('docent-card')
        ->and($html)->not->toContain('javascript:')
        ->and($html)->not->toContain('<a class="docent-card"');
});

it('renders registered components as trusted html', function () {
    $registry = docRegistry();
    $registry->component('widget', new class implements DocumentationComponent
    {
        public function render(DocumentationContext $context, array $attributes): string
        {
            return '<span class="widget">'.$attributes['plan'].'</span>';
        }
    });

    $html = renderHtml('<docs-component name="widget" plan="pro" />', $registry, docContext());

    expect($html)->toContain('<span class="widget">pro</span>');
});

it('resolves includes and guards against cycles', function () {
    // Self-referential include must not recurse infinitely.
    $partial = docParse(":::include name=\"loop\"\n\nPartial body.");

    $html = renderHtml(
        ':::include name="loop"',
        docRegistry(),
        docContext(),
        [],
        fn (string $name) => $name === 'loop' ? $partial : null,
    );

    expect($html)->toContain('Partial body.');
});

it('renders task list items as disabled checkboxes', function () {
    $html = renderHtml("- [x] done\n- [ ] todo", docRegistry(), docContext());

    expect($html)->toContain('<input type="checkbox" disabled checked />')
        ->and($html)->toContain('<input type="checkbox" disabled />');
});

it('renders semantic tables', function () {
    $html = renderHtml("| A | B |\n|---|---|\n| 1 | 2 |", docRegistry(), docContext());

    expect($html)->toContain('<table>')
        ->and($html)->toContain('<thead>')
        ->and($html)->toContain('<th>A</th>')
        ->and($html)->toContain('<tbody>')
        ->and($html)->toContain('<td>1</td>');
});

it('renders callouts with data attributes and titles', function () {
    $html = renderHtml(":::warning title=\"Careful\"\nBe careful.\n:::", docRegistry(), docContext());

    expect($html)->toContain('<div class="docent-callout docent-callout-warning" data-callout="warning">')
        ->and($html)->toContain('<div class="docent-callout-title">Careful</div>');
});
