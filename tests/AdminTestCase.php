<?php

namespace STS\Docent\Tests;

use Illuminate\Support\Facades\Gate;

/**
 * Base case for the admin backend tests: the database store and admin panel are
 * enabled before the app boots (so the admin routes register), and the
 * `viewDocentAdmin` gate is defined to admit only the account owner.
 */
abstract class AdminTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.database.enabled', true);
        $app['config']->set('docent.admin.enabled', true);

        Gate::define('viewDocentAdmin', fn ($user) => (bool) ($user->is_admin ?? false));
    }
}
