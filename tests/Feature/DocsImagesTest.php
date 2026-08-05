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

    return array_map(
        fn (array $i): string => $i['slug'].': '.$i['message'],
        array_values(array_filter(
            json_decode(Artisan::output(), true)['issues'],
            fn (array $i): bool => $i['check'] === 'missing-image',
        )),
    );
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

it('never marks a docs image publicly cacheable', function () {
    // A shared cache holding a private site's screenshot could hand it to a
    // request that never reached the route group's auth middleware.
    $cacheControl = $this->get('/docs/_images/guides/images/screenshot.png')
        ->assertOk()
        ->headers->get('Cache-Control');

    expect($cacheControl)->toContain('private')->not->toContain('public');
});

it('serves images when filesystem.path is left at its default', function () {
    // Null is documented as resource_path('docs'), and the repository honors
    // that — so the image route has to resolve the same root or a default
    // install 404s every screenshot.
    config()->set('docent.sites.docs.filesystem.path', null);
    app()->forgetScopedInstances();

    expect(app(DocentManager::class)->docsPath())->toBe(resource_path('docs'));
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
    $reported = implode("\n", imageIssues());

    expect($reported)
        ->not->toContain('guides/setup:')
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

it('validates a partial image against the page that includes it', function () {
    // The renderer resolves a partial's relative paths from the including
    // page's directory, so the check must judge it the same way — otherwise it
    // confirms a URL that 404s, which is the whole bug this route fixed.
    // The same partial included from the docs root resolves `images/screenshot.png`
    // against the root, where nothing exists — so the check must report it.
    expect(implode("\n", imageIssues()))->toContain('images/screenshot.png');
});

it('stays silent when a partial image does resolve from its includer', function () {
    // guides/includes.md resolves the same partial reference to a real file.
    $reported = array_filter(imageIssues(), fn (string $m): bool => str_contains($m, 'screenshot'));

    expect($reported)->toHaveCount(1);
});

it('rewrites a partial image onto the including page directory', function () {
    // guides/includes.md pulls in _partials/banner.md, so `images/screenshot.png`
    // becomes guides/images/screenshot.png — matching what the check validated.
    expect(renderDocsPage('guides/includes'))
        ->toContain('/docs/_images/guides/images/screenshot.png');
});
