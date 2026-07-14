<?php

declare(strict_types=1);

namespace STS\Docent\Tests;

use Prism\Prism\PrismServiceProvider;

abstract class AiTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PrismServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.ai.enabled', true);
        $app['config']->set('docent.ai.provider', 'fake');
        $app['config']->set('docent.ai.model', 'docent-test');
        $app['config']->set('docent.ai.throttle', '100,1');
        $app['config']->set('docent.widget.enabled', true);
    }
}
