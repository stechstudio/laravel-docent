<?php

namespace STS\Docent\Tests;

use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as Orchestra;
use STS\Docent\DocentServiceProvider;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Tests\Support\PlanUsageComponent;
use Workbench\App\Models\User;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DocentServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The database store is opt-in and not auto-loaded; tests that exercise
        // it enable `docent.database.enabled` and rely on these tables.
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('docent.filesystem.path', dirname(__DIR__).'/tests/fixtures/docs');
        $app['config']->set('docent.name', 'Fixture Docs');
        $app['config']->set('cache.default', 'array');

        Gate::define('billing.manage', fn ($user) => (bool) ($user->is_admin ?? false));
        Gate::define('reports.view', fn ($user) => (bool) ($user->is_admin ?? false));

        $app->make(IntegrationRegistry::class)
            ->value('account.plan', fn () => 'Team Plan', 'Account plan')
            ->link('billing.settings', fn () => '/billing/settings')
            ->component('plan-usage', PlanUsageComponent::class)
            ->condition('beta-features', fn (DocumentationContext $context) => (bool) config('docent_test.beta', false))
            ->audience('internal', fn () => (bool) config('docent_test.internal', false));
    }

    public function adminUser(): User
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@acme.test']);
        $user->is_admin = true;

        return $user;
    }

    public function memberUser(): User
    {
        $user = new User(['name' => 'Member', 'email' => 'member@acme.test']);
        $user->is_admin = false;

        return $user;
    }

    public function contextFor(?User $user): DocumentationContext
    {
        return new DocumentationContext(
            user: $user,
            gate: static fn (string $ability, array $arguments, $viewer): bool => $viewer !== null
                ? Gate::forUser($viewer)->allows($ability, $arguments)
                : Gate::allows($ability, $arguments),
        );
    }
}
