<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function home(Request $request): Response|RedirectResponse
    {
        return $this->render($request, '');
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        return $this->render($request, $slug);
    }

    private function render(Request $request, string $slug): Response|RedirectResponse
    {
        $markdown = false;

        if ($slug === 'index.md') {
            $slug = '';
            $markdown = true;
        } elseif (str_ends_with($slug, '.md')) {
            $slug = substr($slug, 0, -3);
            $markdown = true;
        } elseif (str_contains(strtolower((string) $request->header('Accept')), 'text/markdown')
            && $request->prefers(['text/markdown', 'text/html']) === 'text/markdown') {
            $markdown = true;
        }

        $page = $this->docent->page($slug);

        if ($page === null) {
            abort(404);
        }

        $context = $this->docent->contextFor($request);

        if (! $page->authorize($context)) {
            return $this->denied();
        }

        if ($markdown) {
            return response($this->docent->agentMarkdown($page, $context), 200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Vary' => 'Accept',
            ]);
        }

        if ($page->isLanding()) {
            return response()->view('docent::landing', [
                'docent' => $this->docent,
                'siteName' => $this->docent->siteName(),
                'homeUrl' => $this->docent->url(''),
                'searchEnabled' => (bool) config('docent.search.enabled', true),
                'page' => $page,
                'title' => $page->title(),
                'description' => $page->description(),
                'html' => $page->render($context),
                'heroCta' => $page->heroCta(),
                'sections' => $this->docent->navigationSections($context, $slug),
                'topbarLinks' => $this->docent->topbarLinks($context, $slug),
                'landing' => true,
            ])->header('Link', $this->docent->discoveryLinkHeader())->header('Vary', 'Accept');
        }

        [$prev, $next] = $this->docent->prevNext($slug, $context);
        $sections = $this->docent->navigationSections($context, $slug);

        return response()->view('docent::page', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'homeUrl' => $this->docent->url(''),
            'searchEnabled' => (bool) config('docent.search.enabled', true),
            'page' => $page,
            'title' => $page->title(),
            'description' => $page->description(),
            'breadcrumb' => $this->docent->breadcrumb($slug, $context),
            'html' => $page->render($context),
            'navigation' => $this->docent->sectionNavigation($slug, $context),
            'navigationLinks' => $this->docent->navigationLinks($context, $slug),
            'topbarLinks' => $this->docent->topbarLinks($context, $slug),
            'sections' => $sections,
            'toc' => $page->toc($context),
            'currentSlug' => $slug,
            'prev' => $prev,
            'next' => $next,
        ])->header('Link', $this->docent->discoveryLinkHeader())->header('Vary', 'Accept');
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
