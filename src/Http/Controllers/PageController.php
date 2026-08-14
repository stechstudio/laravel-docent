<?php

declare(strict_types=1);

namespace STS\Docent\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use STS\Docent\Content\AgentFeed;
use STS\Docent\DocentManager;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Page;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\DocumentationMode;

/**
 * Serves documentation pages. Deliberately thin — all resolution, rendering,
 * and authorization live on {@see DocentManager} / {@see Page}.
 */
final class PageController
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly AgentFeed $feed,
        private readonly InsightRecorder $insights,
        private readonly DocumentationMode $mode,
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

        // A share link resolves to the shared page and nothing else. The agent
        // feed is a second rendering path with its own rules about what a
        // viewer may see, and keeping the two in step forever is a worse bet
        // than declining to negotiate: a shared page is for a person reading
        // it, and the header that asks for Markdown is never sent by one.
        if ($this->mode->share()) {
            $markdown = false;
        }

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

            $url = $markdown
                ? $this->docent->markdownUrl($target->slug)
                : $this->docent->fullUrl($target->slug);

            return redirect()->to($this->withQueryString($url, $request), 301);
        }

        if ($markdown) {
            return response($this->feed->agentMarkdown($page, $context), 200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Vary' => 'Accept',
            ]);
        }

        if ($this->mode->share()) {
            $this->insights->pageViewed($slug, 'share');

            return $this->shared($page, $slug, $context);
        }

        $this->insights->pageViewed($slug, 'reader');

        if (($layout = $page->layout()) !== 'docs') {
            return response()->view($this->docent->layoutView($layout), [
                ...$this->payload($request, $page, $slug, $context),
                'layout' => $layout,
                'landing' => true,
                'heroBadge' => $page->heroBadge(),
                'heroCta' => $page->heroCta(),
                'heroSearch' => $page->heroSearch(),
            ])->header('Link', $this->feed->discoveryLinkHeader())->header('Vary', 'Accept');
        }

        [$prev, $next] = $this->docent->prevNext($slug, $context);

        return response()->view('docent::page', [
            ...$this->payload($request, $page, $slug, $context),
            'breadcrumb' => $this->docent->breadcrumb($slug, $context),
            'navigation' => $this->docent->sectionNavigation($slug, $context),
            'navigationLinks' => $this->docent->navigationLinks($context, $slug),
            'toc' => $page->toc($context),
            'prev' => $prev,
            'next' => $next,
        ])->header('Link', $this->feed->discoveryLinkHeader())->header('Vary', 'Accept');
    }

    /**
     * The view data every layout receives — the documented contract for
     * custom host layouts. The docs layout and front-matter layouts each add
     * their own keys on top, but this base is stable for all of them.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request, Page $page, string $slug, DocumentationContext $context): array
    {
        return [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'homeUrl' => $this->docent->url(''),
            'searchEnabled' => (bool) $this->docent->config('search.enabled', true),
            'assistantStateNamespace' => $this->assistantStateNamespace($request, $context),
            'page' => $page,
            'context' => $context,
            'title' => $page->title(),
            'description' => $page->description(),
            'html' => $page->render($context),
            'sections' => $this->docent->navigationSections($context, $slug),
            'topbarLinks' => $this->docent->topbarLinks($context, $slug),
            'currentSlug' => $slug,
            'shareLinks' => $this->docent->sharing()->linksFor($slug, $context->user),
            'shareDefaultDays' => $this->docent->sharing()->defaultDays(),
        ];
    }

    /**
     * One page, on its own, for a reader the application has never met.
     *
     * The page still had to clear `authorize()` above under the guest
     * context, so a share link can only ever reach content a logged-out
     * visitor was already allowed to see. No navigation is built and no
     * neighbouring pages are resolved — there is nowhere else to go from
     * here, which is the point.
     */
    private function shared(Page $page, string $slug, DocumentationContext $context): Response
    {
        return response()->view('docent::share', [
            'docent' => $this->docent,
            'siteName' => $this->docent->siteName(),
            'page' => $page,
            'context' => $context,
            'title' => $page->title(),
            'description' => $page->description(),
            'html' => $page->render($context),
            'currentSlug' => $slug,
            'loginUrl' => $this->loginUrl(),
        ])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Where the "sign in to read everything" offer points. A host that has no
     * `login` route and configures nothing gets no offer rather than a link
     * into nowhere.
     */
    private function loginUrl(): ?string
    {
        $configured = $this->docent->config('share.login_url');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return Route::has('login') ? route('login') : null;
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
            ? $this->docent->assistantStateNamespace($request, $context)
            : null;
    }

    private function withQueryString(string $url, Request $request): string
    {
        $query = $request->getQueryString();

        return $query === null ? $url : $url.(str_contains($url, '?') ? '&' : '?').$query;
    }
}
