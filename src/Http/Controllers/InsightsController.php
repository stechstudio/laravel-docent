<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\Insights\InsightRecorder;

final class InsightsController
{
    public function __construct(
        private readonly InsightRecorder $insights,
    ) {}

    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'event' => ['required', 'in:search_result_clicked,search_no_click'],
            'search_id' => ['required', 'uuid'],
            'target_slug' => ['nullable', 'string', 'max:255'],
        ]);

        $recorded = $this->insights->searchInteraction(
            (string) $validated['event'],
            (string) $validated['search_id'],
            isset($validated['target_slug']) ? (string) $validated['target_slug'] : null,
        );

        abort_unless($recorded, 404);

        return response()->noContent();
    }
}
