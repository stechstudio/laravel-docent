<?php

namespace STS\Docent\Tests;

use STS\Docent\Runtime\IntegrationRegistry;

abstract class WidgetTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.widget.enabled', true);
        $app['config']->set('docent.sites.admin', [
            'name' => 'Admin Docs',
            'route' => ['prefix' => 'admin/docs', 'middleware' => ['web']],
            'filesystem' => ['path' => __DIR__.'/fixtures/docs'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Registered here rather than in the base TestCase: suggestions name
        // pages from this suite's fixture tree, and docent:check (exercised
        // against other fixtures) would rightly flag them as unknown there.
        $this->app->make(IntegrationRegistry::class)
            ->suggest('billing.*', ['welcome', 'billing/secret'])
            ->suggest('*.invoice', ['guides/setup', 'welcome']);
    }
}
