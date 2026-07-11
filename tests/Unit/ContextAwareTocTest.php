<?php

use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Documents\Renderer\TableOfContents;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

function tocFixtureDocument()
{
    return (new MarkdownDocumentParser)->parse(<<<'MD'
    ## Public section

    Everyone sees this.

    :::can ability="billing.manage"
    ## Admin tools

    Secret admin heading.
    :::
    MD);
}

it('omits headings inside conditional blocks without a context', function () {
    $toc = TableOfContents::build(tocFixtureDocument());

    expect(array_map(fn ($e) => $e->title, $toc))->toBe(['Public section']);
});

it('omits gated headings for viewers who fail the check', function () {
    $toc = (new TableOfContents(
        new IntegrationRegistry,
        new DocumentationContext(user: null, gate: fn () => false),
    ))->buildFor(tocFixtureDocument());

    expect(array_map(fn ($e) => $e->title, $toc))->toBe(['Public section']);
});

it('includes gated headings for viewers who pass the check', function () {
    $toc = (new TableOfContents(
        new IntegrationRegistry,
        new DocumentationContext(user: null, gate: fn () => true),
    ))->buildFor(tocFixtureDocument());

    expect(array_map(fn ($e) => $e->title, $toc))->toBe(['Public section', 'Admin tools']);
});
