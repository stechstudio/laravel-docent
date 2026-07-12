<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\DocentManager;

final class WidgetController
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function home(Request $request): Response
    {
        $this->docent->enableWidgetMode();
        $context = $this->docent->contextFor($request);

        return response()->view('docent::widget.home', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'searchEnabled' => (bool) config('docent.search.enabled', true),
            'navigation' => $this->docent->navigation($context),
            'currentSlug' => '',
            'fullDocsUrl' => $this->docent->fullUrl(''),
            'title' => null,
        ]);
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $this->docent->enableWidgetMode();
        $page = $this->docent->page($slug);

        if ($page === null) {
            abort(404);
        }

        $context = $this->docent->contextFor($request);

        if (! $page->authorize($context)) {
            return $this->denied();
        }

        return response()->view('docent::widget.page', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'searchEnabled' => (bool) config('docent.search.enabled', true),
            'page' => $page,
            'title' => $page->title(),
            'description' => $page->description(),
            'html' => $page->render($context),
            'currentSlug' => $slug,
            'fullDocsUrl' => $this->docent->fullUrl($slug),
        ]);
    }

    private function denied(): Response|RedirectResponse
    {
        $response = config('docent.authorization.denied_response', 404);

        return match (true) {
            $response === 403 => abort(403),
            is_string($response) && str_starts_with($response, 'redirect:') => redirect(substr($response, strlen('redirect:'))),
            default => abort(404),
        };
    }
}
