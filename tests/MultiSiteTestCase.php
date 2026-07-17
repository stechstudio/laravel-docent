<?php

declare(strict_types=1);

namespace STS\Docent\Tests;

use Illuminate\Support\Facades\Gate;
use Workbench\App\Models\User;

/** Boots a real public/admin site pair before Docent registers its routes. */
abstract class MultiSiteTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.default', 'public');
        $app['config']->set('docent.database.enabled', ! $this->hasDistinctDomains());
        $app['config']->set('docent.theme.accent', '#0284c7');
        $app['config']->set('docent.sites', [
            'public' => [
                'name' => 'Public Help',
                'description' => 'Public product documentation.',
                'route' => [
                    'prefix' => 'help',
                    'domain' => $this->hasDistinctDomains() ? 'help.example.test' : null,
                    'middleware' => ['web'],
                ],
                'filesystem' => ['path' => __DIR__.'/fixtures/docs'],
                'admin' => [
                    'enabled' => true,
                    'gate' => 'managePublicDocs',
                ],
            ],
            'admin' => [
                'name' => 'Admin Docs',
                'description' => 'Internal administrator documentation.',
                'route' => [
                    'prefix' => 'admin/docs',
                    'domain' => $this->hasDistinctDomains() ? 'admin.example.test' : null,
                    'middleware' => ['web', 'auth'],
                ],
                'filesystem' => ['path' => __DIR__.'/fixtures/admin-docs'],
                'admin' => [
                    'enabled' => true,
                    'gate' => 'manageAdminDocs',
                ],
                'theme' => ['accent' => '#e11d48'],
            ],
        ]);

        Gate::define('managePublicDocs', fn ($user): bool => (bool) ($user->manages_public_docs ?? false));
        Gate::define('manageAdminDocs', fn ($user): bool => (bool) ($user->manages_admin_docs ?? false));
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => 'Login')->name('login');
    }

    protected function hasDistinctDomains(): bool
    {
        return false;
    }

    public function publicDocsEditor(): User
    {
        $user = $this->adminUser();
        $user->manages_public_docs = true;
        $user->manages_admin_docs = false;

        return $user;
    }

    public function adminDocsEditor(): User
    {
        $user = $this->adminUser();
        $user->manages_public_docs = false;
        $user->manages_admin_docs = true;

        return $user;
    }
}
