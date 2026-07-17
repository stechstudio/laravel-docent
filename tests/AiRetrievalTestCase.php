<?php

namespace STS\Docent\Tests;

abstract class AiRetrievalTestCase extends AiTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.sites.docs.filesystem.path', __DIR__.'/fixtures/search-relevance');
        $app['config']->set('docent.ai.retrieval.debug', true);
    }
}
