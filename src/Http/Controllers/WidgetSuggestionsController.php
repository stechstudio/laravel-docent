<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use STS\Docent\DocentManager;

final class WidgetSuggestionsController
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->docent->enableWidgetMode();
        $context = $this->docent->contextFor($request);
        $page = $request->string('page')->trim()->toString();
        $slugs = array_values(array_filter($request->input('slugs', []), 'is_string'));

        return response()->json([
            'suggestions' => match (true) {
                $slugs !== [] => $this->docent->authorizedSuggestions($slugs, $context),
                $page !== '' => $this->docent->widgetSuggestions($page, $context),
                default => [],
            },
            'page' => $page,
        ]);
    }
}
