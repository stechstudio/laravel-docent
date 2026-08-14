<?php

namespace STS\Docent\Tests;

/** Gated docs with the share feature left switched off, which is the default. */
abstract class ShareDisabledTestCase extends ShareTestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('docent.share.enabled', false);
    }
}
