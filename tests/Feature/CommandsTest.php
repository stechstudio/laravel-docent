<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->docsPath = sys_get_temp_dir().'/docent-install-'.uniqid();
    config()->set('docent.sites.docs.filesystem.path', $this->docsPath);
});

afterEach(function () {
    File::deleteDirectory($this->docsPath);
});

it('scaffolds starter docs on install', function () {
    $this->artisan('docent:install')->assertSuccessful();

    expect(File::exists($this->docsPath.'/index.md'))->toBeTrue()
        ->and(File::exists($this->docsPath.'/getting-started/introduction.md'))->toBeTrue();
});

it('never overwrites existing docs on install', function () {
    File::ensureDirectoryExists($this->docsPath);
    File::put($this->docsPath.'/index.md', 'MY CONTENT');

    $this->artisan('docent:install')->assertSuccessful();

    expect(File::get($this->docsPath.'/index.md'))->toBe('MY CONTENT');
});
