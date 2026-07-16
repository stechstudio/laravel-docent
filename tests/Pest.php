<?php

use STS\Docent\Tests\AdminTestCase;
use STS\Docent\Tests\AiRetrievalTestCase;
use STS\Docent\Tests\AiTestCase;
use STS\Docent\Tests\InsightsTestCase;
use STS\Docent\Tests\SearchRelevanceTestCase;
use STS\Docent\Tests\TestCase;
use STS\Docent\Tests\WidgetTestCase;

uses(TestCase::class)->in('Feature');
uses(AdminTestCase::class)->in('Admin');
uses(WidgetTestCase::class)->in('Widget');
uses(AiTestCase::class)->in('Ai');
uses(InsightsTestCase::class)->in('Insights');
uses(AiRetrievalTestCase::class)->in('AiRetrieval');
uses(SearchRelevanceTestCase::class)->in('SearchRelevance');
