<?php

declare(strict_types=1);

namespace STS\Docent\Tests;

use Illuminate\Support\Facades\Gate;

abstract class InsightsTestCase extends AiTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.database.enabled', true);
        $app['config']->set('docent.admin.enabled', true);
        $app['config']->set('docent.insights.enabled', true);

        Gate::define('viewDocentAdmin', fn ($user) => (bool) ($user->is_admin ?? false));
    }
}
