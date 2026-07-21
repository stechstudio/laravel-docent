<?php

use STS\Docent\Ai\Models\AiQuestion;
use STS\Docent\Content\Models\DocentPage;
use STS\Docent\Content\Models\DocentPageRevision;
use STS\Docent\Insights\Models\InsightEvent;

it('allows host apps to extend the persisted models', function () {
    $page = new class extends DocentPage {};
    $revision = new class extends DocentPageRevision {};
    $question = new class extends AiQuestion {};
    $event = new class extends InsightEvent {};

    expect($page)->toBeInstanceOf(DocentPage::class)
        ->and($revision)->toBeInstanceOf(DocentPageRevision::class)
        ->and($question)->toBeInstanceOf(AiQuestion::class)
        ->and($event)->toBeInstanceOf(InsightEvent::class);
});
