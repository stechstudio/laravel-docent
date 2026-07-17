<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use STS\Docent\Insights\Models\InsightEvent;
use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Support\DocentCache;

beforeEach(function () {
    config()->set('docent.sites.docs.filesystem.path', dirname(__DIR__, 2).'/fixtures/clean-docs');
    config()->set('docent.sites.admin', [
        'name' => 'Admin Docs',
        'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
        'filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs'],
    ]);
    $this->resetDocentScope();
});

it('clears every site by default and only one with site selection', function () {
    $sites = $this->app->make(SiteRegistry::class);
    $docs = $sites->serviceFor('docs', DocentCache::class);
    $admin = $sites->serviceFor('admin', DocentCache::class);
    $docsVersion = $docs->version();
    $adminVersion = $admin->version();

    $this->artisan('docent:clear')->assertSuccessful();

    expect($docs->version())->toBeGreaterThan($docsVersion)
        ->and($admin->version())->toBeGreaterThan($adminVersion);

    $before = $admin->version();
    $this->artisan('docent:clear', ['--site' => 'docs'])->assertSuccessful();

    expect($admin->version())->toBe($before);
});

it('prunes every site by default and only one with site selection', function () {
    config()->set('docent.insights.retention_days', 30);

    foreach (['docs', 'admin'] as $site) {
        InsightEvent::query()->create([
            'site' => $site,
            'event_id' => (string) Str::uuid(),
            'category' => 'pages',
            'event' => 'page_viewed',
            'surface' => 'reader',
            'created_at' => now()->subDays(31),
        ]);
    }

    $this->artisan('docent:insights:prune')->assertSuccessful();
    expect(InsightEvent::query()->count())->toBe(0);

    foreach (['docs', 'admin'] as $site) {
        InsightEvent::query()->create([
            'site' => $site,
            'event_id' => (string) Str::uuid(),
            'category' => 'pages',
            'event' => 'page_viewed',
            'surface' => 'reader',
            'created_at' => now()->subDays(31),
        ]);
    }

    $this->artisan('docent:insights:prune', ['--site' => 'docs'])->assertSuccessful();

    expect(InsightEvent::query()->pluck('site')->all())->toBe(['admin']);
});

it('checks only the requested site content', function () {
    config()->set('docent.sites.admin.filesystem.path', dirname(__DIR__, 2).'/fixtures/broken-docs');
    $this->resetDocentScope();

    $this->artisan('docent:check', ['--site' => 'docs'])->assertSuccessful();
    $this->artisan('docent:check')->assertFailed();
});

it('rejects an unknown site option', function (string $command) {
    $this->artisan($command, ['--site' => 'nope'])
        ->expectsOutputToContain('Unknown Docent site [nope]')
        ->assertFailed();
})->with([
    'clear' => 'docent:clear',
    'check' => 'docent:check',
    'prune' => 'docent:insights:prune',
]);

it('installs starter files for the configured default site', function () {
    $path = sys_get_temp_dir().'/docent-install-site-'.uniqid();
    config()->set('docent.default', 'admin');
    config()->set('docent.sites.admin.filesystem.path', $path);
    $this->resetDocentScope();

    try {
        $this->artisan('docent:install')->assertSuccessful();

        expect(File::exists($path.'/index.md'))->toBeTrue()
            ->and(File::exists($path.'/getting-started/introduction.md'))->toBeTrue();
    } finally {
        File::deleteDirectory($path);
    }
});

it('flags a non-docs site without a filesystem path', function () {
    config()->set('docent.sites.admin.filesystem.path', null);
    $this->resetDocentScope();

    $this->artisan('docent:check', ['--site' => 'admin'])
        ->expectsOutputToContain('filesystem.path')
        ->assertFailed();
});

it('flags malformed site keys and an invalid explicit default', function () {
    config()->set('docent.sites', [
        ...config('docent.sites'),
        'bad.key' => ['filesystem' => ['path' => dirname(__DIR__, 2).'/fixtures/docs']],
    ]);
    config()->set('docent.default', 'missing');
    $this->resetDocentScope();

    $this->artisan('docent:check', ['--site' => 'docs'])
        ->expectsOutputToContain('bad.key')
        ->expectsOutputToContain('docent.default')
        ->assertFailed();
});

it('warns when route paths overlap on overlapping domains', function () {
    config()->set('docent.sites.admin.route.prefix', 'docs/internal');
    $this->resetDocentScope();

    $this->artisan('docent:check', ['--site' => 'docs'])
        ->expectsOutputToContain('route-overlap')
        ->assertSuccessful();

    $this->artisan('docent:check', ['--site' => 'docs', '--strict' => true])
        ->assertFailed();
});

it('allows equal route paths on distinct concrete domains', function () {
    config()->set('docent.sites.docs.route.domain', 'public.example.test');
    config()->set('docent.sites.admin.route.domain', 'admin.example.test');
    config()->set('docent.sites.admin.route.prefix', 'docs');
    $this->resetDocentScope();

    $exit = Artisan::call('docent:check', ['--site' => 'docs']);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->not->toContain('route-overlap');
});
