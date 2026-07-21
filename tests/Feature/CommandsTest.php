<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->docsPath = sys_get_temp_dir().'/docent-install-'.uniqid();
    $this->agentsPath = base_path('AGENTS.md');
    $this->claudePath = base_path('CLAUDE.md');
    config()->set('docent.sites.docs.filesystem.path', $this->docsPath);

    File::delete([$this->agentsPath, $this->claudePath]);
});

afterEach(function () {
    File::deleteDirectory($this->docsPath);
    File::delete([$this->agentsPath, $this->claudePath]);
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

it('creates an agent guide pointer on install', function () {
    $this->artisan('docent:install')->assertSuccessful();

    expect(File::exists($this->agentsPath))->toBeTrue()
        ->and(File::get($this->agentsPath))->toContain(
            '<!-- docent:guide start -->',
            'php artisan docent:guide',
        );
});

it('keeps the agent guide pointer idempotent on install reruns', function () {
    $this->artisan('docent:install')->assertSuccessful();
    $this->artisan('docent:install')->assertSuccessful();

    expect(substr_count(File::get($this->agentsPath), '<!-- docent:guide start -->'))->toBe(1);
});

it('appends the agent guide pointer without destroying existing content', function () {
    File::put($this->agentsPath, "# My project\n");

    $this->artisan('docent:install')->assertSuccessful();

    expect(File::get($this->agentsPath))->toContain(
        '# My project',
        '<!-- docent:guide start -->',
    );
});
