<?php

declare(strict_types=1);

use STS\Docent\Facades\Docent;

it('prints the authoring reference followed by the application inventory', function () {
    Docent::value('account.plan', fn () => 'Pro', label: 'Account plan name');
    Docent::condition('beta-features', fn () => true, label: 'Beta program');

    $this->artisan('docent:guide')
        ->expectsOutputToContain('# Writing documentation for this application')
        ->expectsOutputToContain('front matter')
        ->expectsOutputToContain('## Site: docs')
        ->expectsOutputToContain('fixtures/docs')
        ->expectsOutputToContain('`account.plan` — Account plan name')
        ->expectsOutputToContain('beta-features')
        ->expectsOutputToContain('docent:check')
        ->assertExitCode(0);
});

it('covers every site and honors the --site filter', function () {
    config()->set('docent.sites.admin', [
        'name' => 'Admin Docs',
        'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
        'filesystem' => ['path' => dirname(__DIR__, 2).'/tests/fixtures/docs'],
    ]);
    $this->resetDocentScope();

    $this->artisan('docent:guide')
        ->expectsOutputToContain('## Site: docs')
        ->expectsOutputToContain('## Site: admin')
        ->expectsOutputToContain('Admin Docs')
        ->assertExitCode(0);

    $this->artisan('docent:guide', ['--site' => 'admin'])
        ->expectsOutputToContain('## Site: admin')
        ->doesntExpectOutputToContain('## Site: docs')
        ->assertExitCode(0);
});

it('lists site-scoped registrations under their own site', function () {
    config()->set('docent.sites.admin', [
        'name' => 'Admin Docs',
        'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
        'filesystem' => ['path' => dirname(__DIR__, 2).'/tests/fixtures/docs'],
    ]);
    $this->resetDocentScope();

    Docent::site('admin')->value('admin.only', fn () => 'yes', label: 'Admin-only value');

    $this->artisan('docent:guide', ['--site' => 'docs'])
        ->doesntExpectOutputToContain('admin.only')
        ->assertExitCode(0);

    $this->artisan('docent:guide', ['--site' => 'admin'])
        ->expectsOutputToContain('admin.only')
        ->assertExitCode(0);
});

it('rejects an unknown site key', function () {
    $this->artisan('docent:guide', ['--site' => 'nope'])->assertExitCode(1);
});
