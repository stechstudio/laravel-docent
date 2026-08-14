<?php

use STS\Docent\Runtime\DocumentationMode;
use STS\Docent\Sharing\Sharing;

beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__).'/fixtures/image-docs');
});

/**
 * A cryptographically perfect token for any path we like.
 *
 * Nothing in the public API mints one for a route outside the allowlist, which
 * is a large part of why the allowlist holds. These tests deliberately reach
 * past that so the middleware's own refusal is what gets proved, rather than
 * the URL builder's reticence.
 */
function tokenFor(string $url): string
{
    app(DocumentationMode::class)->enableShare(intdiv(time(), 86400) + 5);

    return app(Sharing::class)->decorate($url);
}

function sharedPageHtml(): string
{
    return test()->get(test()->shareUrl('guides/setup'))->assertOk()->getContent();
}

it('carries a credential on the images a shared page needs', function () {
    expect(sharedPageHtml())->toMatch('#/docs/_images/guides/images/screenshot\.png\?s=[A-Za-z0-9_-]+#');
});

it('carries a credential on its stylesheet', function () {
    expect(sharedPageHtml())->toMatch('#/docs/_assets/docent\.css\?v=[a-f0-9]+&(amp;)?s=[A-Za-z0-9_-]+#');
});

it('serves an image to an anonymous reader holding its credential', function () {
    preg_match('#(/docs/_images/guides/images/screenshot\.png\?s=[A-Za-z0-9_-]+)#', sharedPageHtml(), $m);

    $this->get(html_entity_decode($m[1]))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('refuses the same image without a credential', function () {
    $this->get('/docs/_images/guides/images/screenshot.png')->assertRedirect('/login');
});

it('refuses an image credential replayed against a different image', function () {
    preg_match('#\?s=([A-Za-z0-9_-]+)#', sharedPageHtml(), $m);

    $this->get('/docs/_images/guides/images/diagram.svg?s='.$m[1])->assertRedirect('/login');
});

it('refuses a page credential replayed against a different page', function () {
    $token = (string) parse_url($this->shareUrl('guides/setup'), PHP_URL_QUERY);

    $this->get('/docs/index?'.$token)->assertRedirect('/login');
});

it('serves the stylesheet to an anonymous reader holding its credential', function () {
    preg_match('#(/docs/_assets/docent\.css\?[^"]+)#', sharedPageHtml(), $m);

    $this->get(html_entity_decode($m[1]))->assertOk();
});

it('refuses a valid credential on every route outside the allowlist', function (string $path) {
    $this->get(tokenFor('http://localhost'.$path))->assertRedirect('/login');
})->with([
    'search' => '/docs/_search',
    'llms.txt' => '/docs/llms.txt',
    'llms-full.txt' => '/docs/llms-full.txt',
    'sitemap' => '/docs/sitemap.xml',
]);
