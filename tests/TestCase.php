<?php

namespace STS\Docent\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use STS\Docent\DocentServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DocentServiceProvider::class,
        ];
    }
}
