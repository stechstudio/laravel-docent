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
