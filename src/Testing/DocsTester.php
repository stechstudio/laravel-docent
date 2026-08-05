<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Assert;
use STS\Docent\DocentManager;
use STS\Docent\Search\SearchEngine;
use Throwable;

/**
 * Fluent factory for documentation test assertions, scoped to the current site.
 * Reached via {@see InteractsWithDocs::docs()}.
 *
 * `as()` and `forAudience()` set the viewer for everything the tester hands out,
 * including {@see page()} and {@see search()}, so a viewer is stated once.
 */
final class DocsTester
{
    use BuildsTestContext;

    private ?Authenticatable $user = null;

    private ?string $audience = null;

    public function __construct(
        private readonly DocentManager $manager,
    ) {}

    public function page(string $slug): PageAssertions
    {
        return (new PageAssertions($this->manager, $slug))
            ->as($this->user)
            ->forAudience($this->audience);
    }

    /**
     * The viewer for everything this tester produces. Null (the default) is a
     * guest, so `->as(null)` also clears a viewer set earlier.
     */
    public function as(?Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function forAudience(?string $audience): self
    {
        $this->audience = $audience;

        return $this;
    }

    /**
     * Every content page slug in the current site's tree, sorted, using Docent's
     * own derivation — the root `index.md` is the empty slug, `foo/index.md`
     * collapses to `foo`, and partials are not pages.
     *
     * Hidden and locked pages are included: both are ordinary pages a reader can
     * open directly. Redirect stubs are not — a stub is an alias that never
     * renders, so counting it as a page would make "every page renders" mean
     * something other than it says. Unpublished database drafts are absent
     * because the reader repository does not serve them.
     *
     * @return list<string>
     */
    public function pages(): array
    {
        $slugs = [];

        foreach ($this->manager->repository()->all() as $page) {
            if (! $page->redirectStub) {
                $slugs[] = $page->slug;
            }
        }

        sort($slugs);

        return $slugs;
    }

    /**
     * Assert every page the current viewer may open renders without throwing.
     *
     * Pages the viewer cannot see are skipped rather than failed — a sweep for a
     * narrow role is asking "does what this role can reach still work", and a
     * gated page is not in scope for that question. But a sweep that reached
     * nothing at all is a broken test rather than a passing one, so zero
     * rendered pages fails: a misconfigured gate that denies everything would
     * otherwise show up green.
     *
     * Every page is attempted even after one fails, so a broken corpus is
     * reported at once rather than one page per run. Token failures are made
     * strict for the duration, since a resolver that throws is a render defect
     * this assertion exists to surface, whatever the production policy is.
     */
    public function assertAllPagesRender(): self
    {
        $context = $this->testContext($this->user, $this->audience);
        $slugs = $this->pages();
        $failures = [];
        $rendered = 0;

        $strict = config('docent.render.strict_tokens');
        config()->set('docent.render.strict_tokens', true);

        try {
            foreach ($slugs as $slug) {
                try {
                    $page = $this->manager->page($slug);

                    if ($page === null) {
                        $failures[] = $this->label($slug).': enumerated, but the repository no longer resolves it.';

                        continue;
                    }

                    if (! $page->authorize($context)) {
                        continue;
                    }

                    $rendered++;
                    $page->render($context);
                } catch (Throwable $e) {
                    $failures[] = $this->label($slug).': '.$e::class.' — '.$e->getMessage();
                }
            }
        } finally {
            config()->set('docent.render.strict_tokens', $strict);
        }

        if ($failures !== []) {
            Assert::fail(sprintf(
                "Expected every docs page visible to this viewer to render, but %d of %d failed:\n  %s",
                count($failures),
                $rendered,
                implode("\n  ", $failures),
            ));
        }

        Assert::assertGreaterThan(0, $rendered, sprintf(
            'Expected the docs sweep to render at least one page, but all %d were hidden from this viewer. '
            .'A sweep that reaches nothing proves nothing — check the viewer and the gates.',
            count($slugs),
        ));

        return $this;
    }

    public function search(string $query, ?Authenticatable $as = null, ?string $audience = null, int $limit = 20): SearchAssertions
    {
        $results = app(SearchEngine::class)->search($query, $this->testContext($as ?? $this->user, $audience ?? $this->audience), $limit);

        return new SearchAssertions($query, $results);
    }

    private function label(string $slug): string
    {
        return $slug === '' ? '(home)' : $slug;
    }
}
