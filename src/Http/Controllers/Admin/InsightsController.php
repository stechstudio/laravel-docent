<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use STS\Docent\Insights\InsightSummary;

final class InsightsController
{
    public function __construct(
        private readonly InsightSummary $summary,
    ) {}

    public function __invoke(Request $request): View
    {
        $days = min(365, max(1, $request->integer('days', 30)));

        return view('docent::admin-insights', [
            'summary' => $this->summary->forDays($days),
            'days' => $days,
        ]);
    }
}
