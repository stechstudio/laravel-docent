<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\Accordion;
use STS\Docent\Documents\Ast\Frame;
use STS\Docent\Documents\Ast\Step;
use STS\Docent\Documents\Ast\Steps;
use STS\Docent\Documents\Ast\Tab;
use STS\Docent\Documents\Ast\Tabs;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Renderer\AgentMarkdownRenderer;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\SearchTextRenderer;
use STS\Docent\Documents\Renderer\TableOfContents;

require_once __DIR__.'/Helpers.php';

function componentDocument(): Document
{
    return docParse(<<<'MD'
    ::::steps
    :::step Install the package
    Run Composer.
    :::
    :::step Run the migrations
    Apply the schema.
    :::
    ::::

    :::accordion How do refunds work?
    Refunds take 5–10 days.

    ## Refund timing
    :::

    ::::tabs
    :::tab iOS
    Use the App Store.
    :::
    :::tab Android
    ## Android setup

    Use Google Play.
    :::
    ::::

    :::frame caption="The billing overview screen"
    ![Billing overview](billing.png)
    :::
    MD);
}

it('parses all content components and preserves multi-word shorthand labels', function () {
    $document = componentDocument();

    expect(docFind($document, Steps::class))->not->toBeNull()
        ->and(docFindAll($document, Step::class))->toHaveCount(2)
        ->and(docFindAll($document, Step::class)[0]->title)->toBe('Install the package')
        ->and(docFind($document, Accordion::class)->title)->toBe('How do refunds work?')
        ->and(docFind($document, Tabs::class))->not->toBeNull()
        ->and(docFindAll($document, Tab::class)[1]->label)->toBe('Android')
        ->and(docFind($document, Frame::class)->caption)->toBe('The billing overview screen');
});

it('renders semantic, accessible component HTML', function () {
    $html = (new HtmlRenderer(docRegistry(), docContext()))->render(componentDocument());

    expect($html)
        ->toContain('<ol class="docent-steps">')
        ->toContain('docent-step-title">Install the package')
        ->toContain('data-docent-accordion')
        ->toContain('aria-controls="docent-accordion-1-panel"')
        ->toContain('role="tablist"')
        ->toContain('role="tabpanel"')
        ->toContain('x-on:keydown="onKeydown($event, 1)"')
        ->toContain('<figure class="docent-frame"')
        ->toContain('role="dialog"')
        ->toContain('<figcaption>The billing overview screen</figcaption>');
});

it('renders readable agent markdown without UI directives', function () {
    $markdown = (new AgentMarkdownRenderer(docRegistry(), docContext()))
        ->render(componentDocument(), 'Components');

    expect($markdown)
        ->toContain('1. **Install the package**')
        ->toContain('2. **Run the migrations**')
        ->toContain('**How do refunds work?**')
        ->toContain('**iOS**')
        ->toContain('**Android**')
        ->toContain('![Billing overview](')
        ->toContain('*The billing overview screen*')
        ->not->toContain(':::');
});

it('indexes labels and visible nested content but prunes gated component content', function () {
    $document = docParse(<<<'MD'
    ::::steps
    :::step Public setup
    Visible instructions.

    :::can secret.view
    Hidden instructions.
    :::
    :::
    ::::
    MD);

    $text = (new SearchTextRenderer)->render($document);

    expect($text)->toContain('Public setup')
        ->toContain('Visible instructions.')
        ->not->toContain('Hidden instructions.');
});

it('includes headings in accordions and tabs in the table of contents', function () {
    $toc = TableOfContents::build(componentDocument());

    expect(array_column($toc, 'title'))->toBe(['Refund timing', 'Android setup']);
});
