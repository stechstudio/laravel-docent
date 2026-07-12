<?php

use STS\Docent\Documents\Parser\MarkdownDocumentParser;
use STS\Docent\Documents\Renderer\AgentMarkdownRenderer;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

it('turns Docent UI nodes into readable agent markdown', function () {
    $source = <<<'MD'
    # Example

    :::note title="Keep in mind"
    Read the [guide](guide#details).
    :::

    ::::cards
    :::card title="Next step" href="next"
    Continue here.
    :::
    ::::

    Value: {{ value:account.plan }}

    ```php filename="app/Example.php"
    echo 'hello';
    ```

    :::can ability="secret"
    Private detail.
    :::
    MD;

    $registry = (new IntegrationRegistry)->value('account.plan', fn () => 'Resolved', 'Account plan');
    $context = new DocumentationContext(gate: fn (string $ability) => false);
    $renderer = new AgentMarkdownRenderer(
        registry: $registry,
        context: $context,
        baseDir: 'start',
        markdownUrlResolver: fn (string $slug): string => 'https://example.test/docs/'.$slug.'.md',
    );

    $markdown = $renderer->render((new MarkdownDocumentParser)->parse($source), 'Example', 'A useful page.');

    expect($markdown)
        ->toStartWith("# Example\n\n> A useful page.")
        ->toContain('> **Note: Keep in mind**')
        ->toContain('[guide](https://example.test/docs/start/guide.md#details)')
        ->toContain('- [Next step](https://example.test/docs/start/next.md)')
        ->toContain('{Account plan}')
        ->toContain('```php filename="app/Example.php"')
        ->not->toContain('Private detail')
        ->not->toContain(':::');
});
