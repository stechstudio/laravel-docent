<?php

use Illuminate\Support\Facades\File;
use STS\Docent\Content\Repositories\DocumentationRepository;

beforeEach(function () {
    $this->docsPath = sys_get_temp_dir().'/docent-make-'.uniqid();
    config()->set('docent.sites.docs.filesystem.path', $this->docsPath);
});

afterEach(function () {
    File::deleteDirectory($this->docsPath);
});

it('scaffolds a how-to page that passes docent check', function () {
    $this->artisan('docent:make how-to guides/reset-password')->assertSuccessful();

    $path = $this->docsPath.'/guides/reset-password.md';

    expect(File::exists($path))->toBeTrue()
        ->and(File::get($path))->toContain('title: Reset Password', '## Goal');

    app()->forgetInstance(DocumentationRepository::class);

    $this->artisan('docent:check')
        ->expectsOutputToContain('no problems found')
        ->assertSuccessful();
});

it('fails for an unknown content type', function () {
    $this->artisan('docent:make nonsense foo')
        ->expectsOutputToContain('tutorial, how-to, reference, concept')
        ->assertFailed();
});

it('refuses to overwrite without force', function () {
    $path = $this->docsPath.'/guides/reset-password.md';

    $this->artisan('docent:make how-to guides/reset-password')->assertSuccessful();
    File::put($path, 'existing content');

    $this->artisan('docent:make how-to guides/reset-password')
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(File::get($path))->toBe('existing content');

    $this->artisan('docent:make how-to guides/reset-password --force')->assertSuccessful();

    expect(File::get($path))->toContain('title: Reset Password', '## Goal');
});

it('fails for an unknown site', function () {
    $this->artisan('docent:make concept foo --site=nope')
        ->expectsOutputToContain('Unknown Docent site [nope].')
        ->assertFailed();
});
