<?php

namespace STS\Docent\Tests;

abstract class WidgetTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.widget.enabled', true);
    }
}
