<?php

declare(strict_types=1);

use STS\Docent\Documents\Serializer\MarkdownExporter;

require_once __DIR__.'/Helpers.php';

function exportMarkdown(string $markdown): string
{
    return (new MarkdownExporter)->export(docParse($markdown));
}

it('spells headings, emphasis, code, and links', function () {
    $out = exportMarkdown(<<<'MD'
    ## Section

    A **bold** and *italic* and ~~struck~~ word with `code` and a [link](getting-started).
    MD);

    expect($out)->toContain('## Section')
        ->toContain('**bold**')
        ->toContain('*italic*')
        ->toContain('~~struck~~')
        ->toContain('`code`')
        ->toContain('[link](getting-started)');
});

it('spells bullet, ordered, and task lists', function () {
    $out = exportMarkdown(<<<'MD'
    - One
    - Two

    3. Third
    4. Fourth

    - [x] Done
    - [ ] Todo
    MD);

    expect($out)->toContain('- One')
        ->toContain('3. Third')
        ->toContain('4. Fourth')
        ->toContain('- [x] Done')
        ->toContain('- [ ] Todo');
});

it('spells fenced code with language and title', function () {
    $out = exportMarkdown(<<<'MD'
    ```php title="app/Foo.php"
    echo 1;
    ```
    MD);

    expect($out)->toContain('```php title="app/Foo.php"')
        ->toContain('echo 1;');
});

it('spells a GFM table with alignment', function () {
    $out = exportMarkdown(<<<'MD'
    | Left | Center | Right |
    | :--- | :----: | ----: |
    | a    | b      | c     |
    MD);

    expect($out)->toContain('| Left | Center | Right |')
        ->toContain('| :--- | :---: | ---: |')
        ->toContain('| a | b | c |');
});

it('spells callouts, gates, conditions, and audiences', function () {
    $out = exportMarkdown(<<<'MD'
    :::note title="Heads up"
    Note body.
    :::

    :::can ability="billing.manage" arguments="a,b"
    Allowed.
    :::

    :::cannot ability="billing.manage"
    Denied.
    :::

    :::unless condition="beta"
    Hidden in beta.
    :::

    :::audience name="internal"
    Staff only.
    :::
    MD);

    expect($out)->toContain(':::note title="Heads up"')
        ->toContain(':::can ability="billing.manage" arguments="a,b"')
        ->toContain(':::cannot ability="billing.manage"')
        ->toContain(':::unless condition="beta"')
        ->toContain(':::audience name="internal"');
});

it('spells includes, components, values, and app links', function () {
    $out = exportMarkdown(<<<'MD'
    :::include name="permissions-note"

    <docs-component name="plan-usage" plan="pro" />

    Your plan is {{ value:account.plan }} — visit [billing]({{ link:billing.settings }}) or {{ route:dashboard }}.
    MD);

    expect($out)->toContain(':::include name="permissions-note"')
        ->toContain('<docs-component name="plan-usage" plan="pro" />')
        ->toContain('{{ value:account.plan }}')
        ->toContain('[billing]({{ link:billing.settings }})')
        ->toContain('{{ route:dashboard }}');
});

it('lengthens nested directive fences by one colon per level', function () {
    // A gate wrapping a cards group wrapping a card: card=3, cards=4, gate=5.
    $out = exportMarkdown(<<<'MD'
    :::::can ability="billing.manage"
    ::::cards
    :::card title="Getting Started" href="getting-started"
    Install and configure.
    :::
    ::::
    :::::
    MD);

    expect($out)->toContain(':::::can ability="billing.manage"')
        ->toContain('::::cards')
        ->toContain(':::card title="Getting Started" href="getting-started"');
});

it('exports directive fences that reparse to the same structure', function () {
    $markdown = <<<'MD'
    :::::can ability="billing.manage"
    ::::cards
    :::card title="One"
    Body one.
    :::
    :::card title="Two"
    Body two.
    :::
    ::::
    :::::
    MD;

    $exported = exportMarkdown($markdown);

    // Reparsing the export yields the identical AST — the critical fence property.
    expect(docSemanticEquals(docParse($markdown), docParse($exported)))->toBeTrue();
});

it('is deterministic and a fixpoint', function () {
    $markdown = <<<'MD'
    # Title

    A paragraph with a soft
    break and **bold** text.

    :::tip title="Idempotency"
    Retry safely.
    :::

    ```php
    Ledger::post($tx);
    ```
    MD;

    $once = exportMarkdown($markdown);
    $twice = (new MarkdownExporter)->export(docParse($once));

    expect($twice)->toBe($once);
});

it('prepends a front matter block only when asked', function () {
    $document = docParse("# Hi\n\nBody.");

    $bare = (new MarkdownExporter)->export($document);
    $withFm = (new MarkdownExporter)->withFrontMatter(['title' => 'Hi', 'order' => 2])->export($document);

    expect($bare)->not->toContain('---')
        ->and($withFm)->toStartWith("---\n")
        ->and($withFm)->toContain('title: Hi')
        ->and($withFm)->toContain('# Hi');
});
