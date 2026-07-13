<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\CodeGroup;
use STS\Docent\Documents\Ast\Video;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Renderer\AgentMarkdownRenderer;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Renderer\SearchTextRenderer;
use STS\Docent\Documents\Serializer\AstToTiptap;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Support\VideoSource;

require_once __DIR__.'/Helpers.php';

function phaseBComponentsMarkdown(): string
{
    return <<<'MD'
    :::video https://www.youtube.com/watch?v=dQw4w9WgXcQ caption="Reconcile a ledger"
    :::

    :::video https://cdn.example.com/clips/reconciling.mp4 caption="Self-hosted walkthrough"
    :::

    ::::code-group
    ```php filename="routes/web.php"
    Route::get('/billing', BillingController::class);
    ```
    ```bash title="Terminal"
    php artisan route:list --path=billing
    ```
    ```json
    {"ready": true}
    ```
    ::::
    MD;
}

it('classifies supported provider and file video URLs', function (string $url, string $kind, ?string $embedHost, ?string $mimeType) {
    $source = VideoSource::parse($url);

    expect($source)->not->toBeNull()
        ->and($source->kind)->toBe($kind)
        ->and($source->mimeType)->toBe($mimeType);

    if ($embedHost !== null) {
        expect($source->embedUrl)->toContain($embedHost)->toContain('autoplay=1');
    }
})->with([
    'YouTube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'youtube-nocookie.com', null],
    'YouTube short URL' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube', 'youtube-nocookie.com', null],
    'Vimeo' => ['https://vimeo.com/123456789', 'vimeo', 'player.vimeo.com', null],
    'Loom' => ['https://www.loom.com/share/abc_123', 'loom', 'www.loom.com', null],
    'MP4' => ['/clips/demo.mp4', 'file', null, 'video/mp4'],
    'WebM' => ['https://cdn.example.com/demo.webm?version=2', 'file', null, 'video/webm'],
    'Ogg' => ['/clips/demo.ogg', 'file', null, 'video/ogg'],
]);

it('parses videos and code groups with deterministic labels', function () {
    $document = docParse(phaseBComponentsMarkdown());
    $videos = docFindAll($document, Video::class);
    $blocks = docFindAll($document, CodeBlock::class);

    expect($videos)->toHaveCount(2)
        ->and($videos[0]->url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->and($videos[0]->caption)->toBe('Reconcile a ledger')
        ->and(docFind($document, CodeGroup::class))->not->toBeNull()
        ->and($blocks)->toHaveCount(3)
        ->and(array_map(fn (CodeBlock $block): string => $block->label(), $blocks))
        ->toBe(['routes/web.php', 'Terminal', 'Json']);
});

it('renders click-to-load provider facades and native file videos', function () {
    $html = (new HtmlRenderer(docRegistry(), docContext()))->render(docParse(phaseBComponentsMarkdown()));

    expect($html)
        ->toContain('data-docent-video')
        ->toContain('data-embed-url="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1"')
        ->toContain('<button type="button" class="docent-video-facade"')
        ->toContain('aria-label="Play Reconcile a ledger"')
        ->toContain('<template x-if="loaded"><iframe x-bind:src="$root.dataset.embedUrl"')
        ->not->toContain('src="https://www.youtube')
        ->toContain('<video controls preload="metadata">')
        ->toContain('<source src="https://cdn.example.com/clips/reconciling.mp4" type="video/mp4"')
        ->toContain('role="tablist" aria-label="Code examples"')
        ->toContain('routes/web.php')
        ->toContain('Terminal');
});

it('renders videos as links and expands every code example for agents', function () {
    $markdown = (new AgentMarkdownRenderer(docRegistry(), docContext()))
        ->render(docParse(phaseBComponentsMarkdown()), 'Phase B');

    expect($markdown)
        ->toContain('[Reconcile a ledger](https://www.youtube.com/watch?v=dQw4w9WgXcQ)')
        ->toContain('[Self-hosted walkthrough](https://cdn.example.com/clips/reconciling.mp4)')
        ->toContain("**routes/web.php**\n\n```php filename=\"routes/web.php\"")
        ->toContain("**Terminal**\n\n```bash title=\"Terminal\"")
        ->toContain("**Json**\n\n```json")
        ->not->toContain(':::video')
        ->not->toContain('::::code-group');
});

it('indexes captions and code exactly as standalone code blocks', function () {
    $document = docParse(phaseBComponentsMarkdown());
    $text = (new SearchTextRenderer)->render($document);

    expect($text)
        ->toContain('Reconcile a ledger')
        ->toContain('Self-hosted walkthrough')
        ->toContain("Route::get('/billing', BillingController::class);")
        ->toContain('php artisan route:list --path=billing')
        ->not->toContain('youtube.com');
});

it('round-trips phase B components through Tiptap and canonical Markdown', function () {
    $original = docParse(phaseBComponentsMarkdown());
    $tiptap = (new AstToTiptap)->convert($original);
    $roundTripped = (new TiptapDocumentParser)->parse(json_encode($tiptap, JSON_THROW_ON_ERROR));
    $exported = (new MarkdownExporter)->export($roundTripped);

    expect($tiptap['content'][0]['type'])->toBe('docsVideo')
        ->and($tiptap['content'][2]['type'])->toBe('docsCodeGroup')
        ->and($tiptap['content'][2]['content'][0]['attrs']['filename'])->toBe('routes/web.php')
        ->and($tiptap['content'][2]['content'][1]['attrs']['title'])->toBe('Terminal')
        ->and(docSemanticEquals($original, $roundTripped))->toBeTrue()
        ->and($exported)->toContain(':::video https://www.youtube.com/watch?v=dQw4w9WgXcQ caption="Reconcile a ledger"')
        ->toContain('::::code-group');
});

it('rejects malformed provider IDs and unknown video sources', function () {
    expect(VideoSource::parse('https://www.youtube.com/watch?v=not/a/safe/id'))->toBeNull()
        ->and(VideoSource::parse('https://example.com/watch/123'))->toBeNull()
        ->and(VideoSource::parse(''))->toBeNull();
});
