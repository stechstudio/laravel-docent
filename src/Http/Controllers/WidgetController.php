<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use STS\Docent\DocentManager;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Runtime\DocumentationContext;

final class WidgetController
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly InsightRecorder $insights,
    ) {}

    public function home(Request $request): Response
    {
        $this->docent->enableWidgetMode();
        $context = $this->docent->contextFor($request);
        $this->insights->pageViewed('', 'widget');

        return response()->view('docent::widget.home', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'searchEnabled' => (bool) $this->docent->config('search.enabled', true),
            'assistantStateNamespace' => $this->assistantStateNamespace($request, $context),
            'navigation' => $this->docent->navigation($context),
            'navigationLinks' => $this->docent->navigationLinks($context),
            'sections' => $this->docent->navigationSections($context),
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

        if ($page->isRedirect()) {
            $target = $this->docent->redirectTarget($page, $context);

            if ($target === null) {
                abort(404);
            }

            $url = $this->docent->widgetUrl($target->slug);
            $query = $request->getQueryString();

            return redirect()->to($query === null ? $url : $url.'?'.$query, 301);
        }

        $this->insights->pageViewed($slug, 'widget');

        return response()->view('docent::widget.page', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'searchEnabled' => (bool) $this->docent->config('search.enabled', true),
            'assistantStateNamespace' => $this->assistantStateNamespace($request, $context),
            'page' => $page,
            'title' => $page->title(),
            'description' => $page->description(),
            'html' => $page->render($context),
            'currentSlug' => $slug,
            'fullDocsUrl' => $this->docent->fullUrl($slug),
        ]);
    }

    private function denied(): RedirectResponse
    {
        $response = $this->docent->config('authorization.denied_response', 404);

        return match (true) {
            $response === 403 => abort(403),
            is_string($response) && str_starts_with($response, 'redirect:') => redirect(substr($response, strlen('redirect:'))),
            default => abort(404),
        };
    }

    private function assistantStateNamespace(Request $request, DocumentationContext $context): ?string
    {
        return $this->docent->config('ai.enabled', false)
            ? $this->docent->assistantStateNamespace($request, $context, true)
            : null;
    }
}
