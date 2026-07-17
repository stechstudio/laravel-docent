<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs($this->adminUser());
});

it('stores an uploaded image on the admin disk and returns its url and path', function () {
    Storage::fake('public');

    $response = $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->image('diagram.png', 400, 300),
    ])->assertCreated();

    $path = $response->json('path');

    expect($path)->toStartWith('docent/');
    Storage::disk('public')->assertExists($path);
    expect($response->json('url'))->toContain($path);
});

it('rejects a non-image upload', function () {
    Storage::fake('public');

    $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('rejects an image over the size limit', function () {
    Storage::fake('public');

    $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->create('huge.png', 6000, 'image/png'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('accepts svg uploads for contained image delivery', function () {
    Storage::fake('public');

    $response = $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->createWithContent('diagram.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
                <circle cx="5" cy="5" r="4" />
            </svg>
            SVG),
    ])->assertCreated();

    expect($response->json('path'))->toEndWith('.svg');
    Storage::disk('public')->assertExists($response->json('path'));

    $stored = Storage::disk('public')->get($response->json('path'));
    expect($stored)->toContain('<circle');
});

it('strips active content from an uploaded svg before storage', function () {
    Storage::fake('public');

    $response = $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->createWithContent('sneaky.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="window.svgExecuted = true">
                <script>window.svgExecuted = true</script>
                <circle cx="5" cy="5" r="4" onclick="window.svgExecuted = true" />
                <a xlink:href="javascript:alert(1)"><rect width="10" height="10" /></a>
                <foreignObject><body xmlns="http://www.w3.org/1999/xhtml" onload="window.svgExecuted = true" /></foreignObject>
            </svg>
            SVG),
    ])->assertCreated();

    // The stored bytes must be inert even if a host serves the disk directly
    // (storage:link) and bypasses the streaming route's protective headers.
    $stored = Storage::disk('public')->get($response->json('path'));
    expect($stored)
        ->toContain('<circle')
        ->toContain('<rect')
        ->not->toContain('<script')
        ->not->toContain('onload')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('foreignObject');
});

it('serves the uploaded image through the docs _uploads streaming route', function () {
    Storage::fake('public');

    $response = $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->image('diagram.png', 400, 300),
    ])->assertCreated();
    $path = $response->json('path');

    // The URL is the streaming route (works on any disk, no storage:link)...
    expect($response->json('url'))->toContain('/docs/_uploads/docent/');

    // ...and it streams the file back with long-lived private caching.
    $this->get($response->json('url'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, private')
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename='.basename($path))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('allows explicit public immutable caching for public documentation', function () {
    Storage::fake('public');
    config()->set('docent.sites.docs.admin.uploads.public_cache', true);
    Storage::disk('public')->put('docent/public.webp', 'image');

    $this->get('/docs/_uploads/docent/public.webp')
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('Content-Type', 'image/webp');
});

it('contains active svg when it is opened as a document', function () {
    Storage::fake('public');
    Storage::disk('public')->put('docent/danger.svg', <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" onload="window.svgExecuted = true">
            <script>window.svgExecuted = true</script>
            <foreignObject><body xmlns="http://www.w3.org/1999/xhtml" onload="window.svgExecuted = true" /></foreignObject>
        </svg>
        SVG);

    $this->get('/docs/_uploads/docent/danger.svg')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Disposition', 'inline; filename=danger.svg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader(
            'Content-Security-Policy',
            "sandbox; default-src 'none'; style-src 'unsafe-inline'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
        );
});

it('404s uploads-route requests outside the docent directory', function () {
    Storage::fake('public');
    Storage::disk('public')->put('elsewhere/file.png', 'x');

    $this->get('/docs/_uploads/elsewhere/file.png')->assertNotFound();
    $this->get('/docs/_uploads/docent/missing.png')->assertNotFound();
});

it('refuses to serve non-image files from the upload directory', function () {
    Storage::fake('public');
    Storage::disk('public')->put('docent/page.html', '<script>alert(1)</script>');

    $this->get('/docs/_uploads/docent/page.html')->assertNotFound();
});
