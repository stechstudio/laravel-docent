<?php

declare(strict_types=1);

namespace STS\Docent\Tests;

abstract class MultiSiteDomainTestCase extends MultiSiteTestCase
{
    protected function hasDistinctDomains(): bool
    {
        return true;
    }
}
