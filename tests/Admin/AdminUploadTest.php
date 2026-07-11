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

it('serves the uploaded image through the docs _uploads streaming route', function () {
    Storage::fake('public');

    $response = $this->postJson('/docs/admin/api/uploads', [
        'file' => UploadedFile::fake()->image('diagram.png', 400, 300),
    ])->assertCreated();

    // The URL is the streaming route (works on any disk, no storage:link)...
    expect($response->json('url'))->toContain('/docs/_uploads/docent/');

    // ...and it streams the file back with long-lived caching.
    $this->get($response->json('url'))
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
});

it('404s uploads-route requests outside the docent directory', function () {
    Storage::fake('public');
    Storage::disk('public')->put('elsewhere/file.png', 'x');

    $this->get('/docs/_uploads/elsewhere/file.png')->assertNotFound();
    $this->get('/docs/_uploads/docent/missing.png')->assertNotFound();
});
