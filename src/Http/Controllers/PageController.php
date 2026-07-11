<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use STS\Docent\DocentManager;
use STS\Docent\Page;

/**
 * Serves documentation pages. Deliberately thin — all resolution, rendering,
 * and authorization live on {@see DocentManager} / {@see Page}.
 */
final class PageController
{
    public function __construct(
        private readonly DocentManager $docent,
    ) {}

    public function home(Request $request): View|Response|RedirectResponse
    {
        return $this->render($request, '');
    }

    public function show(Request $request, string $slug): View|Response|RedirectResponse
    {
        return $this->render($request, $slug);
    }

    private function render(Request $request, string $slug): View|Response|RedirectResponse
    {
        $page = $this->docent->page($slug);

        if ($page === null) {
            abort(404);
        }

        $context = $this->docent->contextFor($request);

        if (! $page->authorize($context)) {
            return $this->denied();
        }

        [$prev, $next] = $this->docent->prevNext($slug, $context);

        return view('docent::page', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'homeUrl' => $this->docent->url(''),
            'searchEnabled' => (bool) config('docent.search.enabled', true),
            'page' => $page,
            'title' => $page->title(),
            'description' => $page->description(),
            'breadcrumb' => $this->docent->breadcrumb($slug, $context),
            'html' => $page->render($context),
            'navigation' => $this->docent->navigation($context),
            'toc' => $page->toc($context),
            'currentSlug' => $slug,
            'prev' => $prev,
            'next' => $next,
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
