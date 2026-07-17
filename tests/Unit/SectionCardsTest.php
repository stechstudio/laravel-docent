<?php

require_once __DIR__.'/Helpers.php';

use STS\Docent\Documents\Ast\SectionCards;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\SectionCardsHtml;
use STS\Docent\Documents\Serializer\AstToTiptap;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Navigation\SectionCard;

it('parses a bare section-cards directive with defaults', function () {
    $doc = docParse(":::section-cards\n:::");

    $node = docFind($doc, SectionCards::class);

    expect($node)->not->toBeNull()
        ->and($node->section)->toBe('')
        ->and($node->columns)->toBe(3);
});

it('parses a shorthand section and a columns attribute', function () {
    $doc = docParse(":::section-cards billing columns=\"2\"\n:::");

    $node = docFind($doc, SectionCards::class);

    expect($node->section)->toBe('billing')
        ->and($node->columns)->toBe(2);
});

it('exports back to canonical markdown and is a fixpoint', function () {
    $markdown = ":::section-cards billing columns=\"2\"\n:::\n";
    $exported = (new MarkdownExporter)->export(docParse($markdown));

    expect($exported)->toBe($markdown)
        ->and((new MarkdownExporter)->export(docParse($exported)))->toBe($exported);
});

it('omits default columns on export', function () {
    $exported = (new MarkdownExporter)->export(docParse(":::section-cards\n:::"));

    expect($exported)->toBe(":::section-cards\n:::\n");
});

it('round-trips through the Tiptap schema', function () {
    $doc = docParse(":::section-cards guides columns=\"4\"\n:::");

    $tiptap = (new AstToTiptap)->convert($doc);
    $block = $tiptap['content'][0];

    expect($block['type'])->toBe('docsSectionCards')
        ->and($block['attrs'])->toBe(['section' => 'guides', 'columns' => 4]);

    $parsed = (new TiptapDocumentParser)->parse(json_encode($tiptap));
    $node = docFind($parsed, SectionCards::class);

    expect($node->section)->toBe('guides')
        ->and($node->columns)->toBe(4);
});

it('renders through the section cards resolver', function () {
    $doc = docParse(":::section-cards\n:::");

    $renderer = new HtmlRenderer(
        registry: docRegistry(),
        context: docContext(),
        sectionCardsRenderer: fn (SectionCards $node): string => SectionCardsHtml::render([
            new SectionCard('guides', 'Guides', '/docs/guides', 'Learn the product.', 'book', 3),
        ], $node->columns),
    );

    $html = $renderer->render($doc);

    expect($html)->toContain('docent-cards')
        ->toContain('data-columns="3"')
        ->toContain('href="/docs/guides"')
        ->toContain('Guides')
        ->toContain('Learn the product.')
        ->toContain('3 articles')
        ->toContain('docent-card-count');
});

it('renders nothing without a resolver', function () {
    $renderer = new HtmlRenderer(registry: docRegistry(), context: docContext());

    expect($renderer->render(docParse(":::section-cards\n:::")))->toBe('');
});

it('pluralizes a single article count', function () {
    $html = SectionCardsHtml::render([
        new SectionCard('a', 'A', '/docs/a', null, null, 1),
    ], 2);

    expect($html)->toContain('1 article')
        ->not->toContain('1 articles');
});
