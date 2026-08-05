<?php

use Illuminate\Support\Facades\Artisan;
use STS\Docent\Content\AgentFeed;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;

beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/image-docs');
});

function renderDocsPage(string $slug): string
{
    $docent = app(DocentManager::class);

    return $docent->page($slug)->render($docent->guestContext());
}

/**
 * @return array<int, string>
 */
function imageIssues(): array
{
    app()->forgetInstance(DocumentationRepository::class);
    Artisan::call('docent:check', ['--format' => 'json']);

    return array_column(array_filter(
        json_decode(Artisan::output(), true)['issues'],
        fn (array $i): bool => $i['check'] === 'missing-image',
    ), 'message');
}

it('rewrites a page-relative image onto the docs image route', function () {
    expect(renderDocsPage('guides/setup'))
        ->toContain('/docs/_images/guides/images/screenshot.png');
});

it('resolves a leading ./ the same way', function () {
    expect(renderDocsPage('guides/setup'))
        ->toContain('/docs/_images/guides/images/diagram.svg');
});

it('resolves ../ against the page directory', function () {
    expect(renderDocsPage('guides/setup'))
        ->toContain('/docs/_images/shared/logo.png');
});

it('leaves external image sources untouched', function () {
    expect(renderDocsPage('guides/setup'))
        ->toContain('https://example.com/remote.png')
        ->not->toContain('_images/https');
});

it('serves the image through the route', function () {
    $this->get('/docs/_images/guides/images/screenshot.png')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('sends a restrictive document policy for svg', function () {
    $response = $this->get('/docs/_images/guides/images/diagram.svg');

    $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->headers->get('Content-Security-Policy'))->toContain("default-src 'none'");
});

it('refuses to serve a path climbing out of the docs directory', function () {
    $this->get('/docs/_images/../../../composer.json')->assertNotFound();
    $this->get('/docs/_images/guides/../../composer.json')->assertNotFound();
});

it('refuses to serve a file type outside the image allowlist', function () {
    // The markdown source sits in the docs root and must never be streamed.
    $this->get('/docs/_images/index.md')->assertNotFound();
});

it('404s a missing image rather than erroring', function () {
    $this->get('/docs/_images/guides/images/nope.png')->assertNotFound();
});

it('answers a conditional request with 304', function () {
    $modified = gmdate('D, d M Y H:i:s \G\M\T', filemtime(
        dirname(__DIR__).'/fixtures/image-docs/guides/images/screenshot.png',
    ) ?: 0);

    $this->get('/docs/_images/guides/images/screenshot.png', ['If-Modified-Since' => $modified])
        ->assertStatus(304);
});

it('accepts relative images that now genuinely resolve', function () {
    expect(implode("\n", imageIssues()))
        ->not->toContain('screenshot.png')
        ->not->toContain('logo.png')
        ->not->toContain('diagram.svg');
});

it('still reports a relative image that is not on disk', function () {
    expect(implode("\n", imageIssues()))->toContain('images/nope.png');
});

it('reports a relative image that escapes the docs directory', function () {
    expect(implode("\n", imageIssues()))->toContain('resolves outside the documentation directory');
});

it('reports a relative reference Docent cannot serve', function () {
    expect(implode("\n", imageIssues()))->toContain('not a file type Docent serves');
});

it('emits absolute image urls in the agent markdown feed', function () {
    $docent = app(DocentManager::class);
    $markdown = app(AgentFeed::class)->agentMarkdown($docent->page('guides/setup'), $docent->guestContext());

    expect($markdown)->toContain('http://localhost/docs/_images/guides/images/screenshot.png');
});

it('keeps image slugs out of reach as pages', function () {
    // `_`-prefixed segments are reserved routes, so no page can shadow them.
    $this->get('/docs/_images')->assertNotFound();
});
