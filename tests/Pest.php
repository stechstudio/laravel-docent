<?php

use STS\Docent\Tests\AdminTestCase;
use STS\Docent\Tests\AiRetrievalTestCase;
use STS\Docent\Tests\AiTestCase;
use STS\Docent\Tests\InsightsTestCase;
use STS\Docent\Tests\MultiSiteDomainTestCase;
use STS\Docent\Tests\MultiSiteTestCase;
use STS\Docent\Tests\SearchRelevanceTestCase;
use STS\Docent\Tests\ShareAnchorTestCase;
use STS\Docent\Tests\ShareDisabledTestCase;
use STS\Docent\Tests\ShareGuardTestCase;
use STS\Docent\Tests\ShareStackTestCase;
use STS\Docent\Tests\ShareTestCase;
use STS\Docent\Tests\TestCase;
use STS\Docent\Tests\WidgetTestCase;

uses(TestCase::class)->in('Feature');
uses(AdminTestCase::class)->in('Admin');
uses(WidgetTestCase::class)->in('Widget');
uses(AiTestCase::class)->in('Ai');
uses(InsightsTestCase::class)->in('Insights');
uses(AiRetrievalTestCase::class)->in('AiRetrieval');
uses(SearchRelevanceTestCase::class)->in('SearchRelevance');
uses(ShareTestCase::class)->in('Share');
uses(ShareDisabledTestCase::class)->in('ShareDisabled');
uses(ShareStackTestCase::class)->in('ShareStack');
uses(ShareGuardTestCase::class)->in('ShareGuard');
uses(ShareAnchorTestCase::class)->in('ShareAnchor');
uses(MultiSiteTestCase::class)->in('MultiSite');
uses(MultiSiteDomainTestCase::class)->in('MultiSiteDomains');
