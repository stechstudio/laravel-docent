<?php

declare(strict_types=1);

use STS\Docent\Ai\AiAnswerRenderer;

function renderAiAnswer(string $markdown): string
{
    return (new AiAnswerRenderer)->render($markdown, [[
        'url' => 'https://app.test/docs/video',
    ]]);
}

it('renders useful markdown while stripping raw html', function () {
    $html = renderAiAnswer(<<<'MARKDOWN'
    ## Add a video

    Use **Video** with `src`:

    - Add a source.
    - Add a title.

    ```html
    <video src="demo.mp4"></video>
    ```

    > Keep captions enabled.

    <script>alert('no')</script>
    <img src="x" onerror="alert('no')">
    MARKDOWN);

    expect($html)
        ->toContain('<h2>Add a video</h2>')
        ->toContain('<strong>Video</strong>')
        ->toContain('<code>src</code>')
        ->toContain('<ul>')
        ->toContain('<pre><code class="language-html">')
        ->toContain('<blockquote>')
        ->not->toContain('<script')
        ->not->toContain('onerror')
        ->not->toContain("alert('no')");
});

it('links only exact viewer-visible citations', function () {
    $html = renderAiAnswer(
        '[Allowed](https://app.test/docs/video) '
        .'[Hallucinated](https://evil.test/docs/video) '
        .'[Almost allowed](https://app.test/docs/video?admin=1) '
        .'[Unsafe](javascript:alert(1)) '
        .'[Data](data:text/html,hello)',
    );

    expect($html)
        ->toContain('href="https://app.test/docs/video"')
        ->toContain('data-docent-assistant-citation=""')
        ->not->toContain('href="https://evil.test')
        ->not->toContain('href="https://app.test/docs/video?admin=1"')
        ->not->toContain('href="javascript:')
        ->not->toContain('href="data:')
        ->toContain('Hallucinated')
        ->toContain('Almost allowed')
        ->toContain('Unsafe')
        ->toContain('Data');
});

it('renders malformed markdown without trusting unfinished markup', function () {
    $html = renderAiAnswer("[unfinished](https://app.test/docs/video\n\n<svg onload=alert(1)>");

    expect($html)
        ->toContain('[unfinished]')
        ->not->toContain('<svg')
        ->not->toContain('onload');
});

it('never loads model-authored images', function () {
    $html = renderAiAnswer('![Private diagram](https://evil.test/tracker.png)');

    expect($html)->toContain('Private diagram')->not->toContain('<img');
});
