<?php

declare(strict_types=1);

use STS\Docent\Documents\Renderer\ContentHtmlSanitizer;

it('preserves useful documentation html while removing active content', function () {
    $html = (new ContentHtmlSanitizer)->sanitize(<<<'HTML'
        <section class="example" aria-label="Example">
            <a href="../guide">Relative guide</a>
            <img src="/diagram.png" alt="Diagram" onerror="window.imageExecuted = true">
            <div style="position: fixed">Styled text</div>
            <a href="javascript:alert(1)" onclick="window.clicked = true">Unsafe link</a>
            <iframe src="https://example.com"></iframe>
            <script>window.scriptExecuted = true</script>
        </section>
        HTML);

    expect($html)->toContain('<section class="example" aria-label="Example">')
        ->toContain('<a href="../guide">Relative guide</a>')
        ->toContain('<img src="/diagram.png" alt="Diagram" />')
        ->toContain('<div>Styled text</div>')
        ->toContain('<a>Unsafe link</a>')
        ->not->toContain('onerror')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('<iframe')
        ->not->toContain('<script')
        ->not->toContain('scriptExecuted');
});
