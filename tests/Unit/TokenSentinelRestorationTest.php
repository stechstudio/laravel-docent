<?php

use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;

it('preserves token syntax verbatim inside fenced code blocks', function () {
    $document = (new MarkdownDocumentParser)->parse(<<<'MD'
    ```markdown
    Your plan is {{ value:plan }} via {{ link:billing.settings }}.
    ```
    MD);

    $code = $document->children[0];

    expect($code)->toBeInstanceOf(CodeBlock::class)
        ->and($code->code)->toBe("Your plan is {{ value:plan }} via {{ link:billing.settings }}.\n")
        ->and($code->code)->not->toContain("\x1F");
});

it('preserves token syntax verbatim inside inline code', function () {
    $document = (new MarkdownDocumentParser)->parse('Use `{{ value:plan arg1 arg2 }}` in your docs.');

    $inline = collect($document->children[0]->children)->first(fn ($n) => $n instanceof InlineCode);

    expect($inline->code)->toBe('{{ value:plan arg1 arg2 }}');
});

it('never leaks the sentinel into front matter values', function () {
    $document = (new MarkdownDocumentParser)->parse(<<<'MD'
    ---
    title: About {{ value:plan }}
    ---

    Body.
    MD);

    expect($document->frontMatter->title())->toBe('About {{ value:plan }}');
});
