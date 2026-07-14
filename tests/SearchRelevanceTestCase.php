<?php

namespace STS\Docent\Tests;

abstract class SearchRelevanceTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.filesystem.path', __DIR__.'/fixtures/search-relevance');
    }
}
