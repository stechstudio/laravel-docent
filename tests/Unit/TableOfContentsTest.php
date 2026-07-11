<?php

declare(strict_types=1);

use STS\Docent\Documents\Renderer\TableOfContents;

require_once __DIR__.'/Helpers.php';

it('builds a nested tree of h2/h3 entries', function () {
    $doc = docParse(<<<'MD'
    # Page Title

    ## First Section

    ### Sub One

    ### Sub Two

    ## Second Section

    #### Too Deep
    MD);

    $toc = TableOfContents::build($doc);

    expect($toc)->toHaveCount(2)
        ->and($toc[0]->title)->toBe('First Section')
        ->and($toc[0]->slug)->toBe('first-section')
        ->and($toc[0]->level)->toBe(2)
        ->and($toc[0]->children)->toHaveCount(2)
        ->and($toc[0]->children[0]->title)->toBe('Sub One')
        ->and($toc[1]->title)->toBe('Second Section')
        // h1 excluded (below min level); h4 excluded (beyond max depth).
        ->and($toc[1]->children)->toBe([]);
});

it('respects a configurable depth', function () {
    $doc = docParse("## A\n\n### B");

    $toc = TableOfContents::build($doc, minLevel: 2, maxDepth: 2);

    expect($toc)->toHaveCount(1)
        ->and($toc[0]->children)->toBe([]);
});
