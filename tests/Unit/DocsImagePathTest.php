<?php

use STS\Docent\Support\DocsImagePath;

$root = fn (): string => dirname(__DIR__).'/fixtures/image-docs';

it('normalizes a page-relative reference against its directory', function () {
    expect(DocsImagePath::relative('images/shot.png', 'guides'))->toBe('guides/images/shot.png')
        ->and(DocsImagePath::relative('./images/shot.png', 'guides'))->toBe('guides/images/shot.png')
        ->and(DocsImagePath::relative('../shared/logo.png', 'guides'))->toBe('shared/logo.png')
        ->and(DocsImagePath::relative('logo.png', ''))->toBe('logo.png');
});

it('strips a query string or fragment', function () {
    expect(DocsImagePath::relative('shot.png?v=2', 'guides'))->toBe('guides/shot.png')
        ->and(DocsImagePath::relative('shot.png#top', 'guides'))->toBe('guides/shot.png');
});

it('declines references that are not page-relative', function () {
    expect(DocsImagePath::relative('/img/shot.png', 'guides'))->toBeNull()
        ->and(DocsImagePath::relative('https://example.com/a.png', 'guides'))->toBeNull()
        ->and(DocsImagePath::relative('//example.com/a.png', 'guides'))->toBeNull()
        ->and(DocsImagePath::relative('data:image/png;base64,AAA', 'guides'))->toBeNull()
        ->and(DocsImagePath::relative('#anchor', 'guides'))->toBeNull()
        ->and(DocsImagePath::relative('', 'guides'))->toBeNull();
});

it('declines a reference that climbs above the docs root', function () {
    expect(DocsImagePath::relative('../../etc/passwd.png', 'guides'))->toBeNull()
        ->and(DocsImagePath::relative('../secrets.png', ''))->toBeNull();
});

it('resolves a real file inside the root', function () use ($root) {
    expect(DocsImagePath::file($root(), 'guides/images/screenshot.png'))
        ->toBe(realpath($root().'/guides/images/screenshot.png'));
});

it('refuses traversal even when the path reaches the resolver unnormalized', function () use ($root) {
    // The HTTP layer may normalize `..` before routing; this is the guarantee
    // that does not depend on it.
    expect(DocsImagePath::file($root(), '../../composer.json'))->toBeNull()
        ->and(DocsImagePath::file($root(), 'guides/../../composer.json'))->toBeNull()
        ->and(DocsImagePath::file($root(), 'guides/images/../../../composer.json'))->toBeNull();
});

it('refuses a real file whose extension is not servable', function () use ($root) {
    expect(DocsImagePath::file($root(), 'index.md'))->toBeNull()
        ->and(DocsImagePath::file($root(), 'guides/setup.md'))->toBeNull();
});

it('refuses a missing file', function () use ($root) {
    expect(DocsImagePath::file($root(), 'guides/images/nope.png'))->toBeNull();
});

it('maps servable extensions to content types, case-insensitively', function () {
    expect(DocsImagePath::mimeType('a/b.PNG'))->toBe('image/png')
        ->and(DocsImagePath::mimeType('a/b.svg'))->toBe('image/svg+xml')
        ->and(DocsImagePath::mimeType('a/b.jpeg'))->toBe('image/jpeg')
        ->and(DocsImagePath::mimeType('a/b.md'))->toBeNull()
        ->and(DocsImagePath::mimeType('a/b'))->toBeNull();
});
