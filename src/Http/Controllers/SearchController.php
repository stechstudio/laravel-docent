<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Docent\DocentManager;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Search\SearchEngine;

/**
 * The docs search endpoint. Authorization is enforced inside {@see SearchEngine}
 * against the current request's context, so results never leak pages the viewer
 * could not open.
 */
final class SearchController
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly SearchEngine $engine,
        private readonly InsightRecorder $insights,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $widget = $request->string('mode')->toString() === 'widget' && config('docent.widget.enabled', false);

        if ($widget) {
            $this->docent->enableWidgetMode();
        }

        $query = $request->string('q')->toString();

        $results = $this->engine->search($query, $this->docent->contextFor($request));
        $searchId = $this->insights->searchSubmitted($query, $results, $widget ? 'widget' : 'reader');

        $payload = [
            'results' => array_map(fn ($result): array => $result->toArray(), $results),
            'query' => $query,
        ];

        if ($searchId !== null) {
            $payload['insight_id'] = $searchId;
            $payload['insights_url'] = route('docent.insights.store');
        }

        return response()->json($payload);
    }
}
