<?php

namespace STS\Docent\Tests\Support;

use STS\Docent\Tests\TestCase;

/**
 * Boots the package with a non-default route prefix so tests can prove the
 * route configuration is honored.
 */
abstract class CustomRouteTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.sites.docs.route.prefix', 'handbook');
        $app['config']->set('docent.sites.docs.route.middleware', ['web']);
    }
}
