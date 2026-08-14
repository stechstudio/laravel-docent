<?php

namespace STS\Docent\Tests;

/**
 * Documentation routed through a guard that is not the application's default.
 *
 * `$request->user()` answers for the default guard alone, so this is the shape
 * that used to hand a signed-in admin the anonymous render instead of their
 * own page.
 */
abstract class ShareGuardTestCase extends ShareTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('docent.sites.docs.route.middleware', ['web', 'auth:admin']);
    }
}
