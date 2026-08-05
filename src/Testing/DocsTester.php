<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Assert;
use STS\Docent\Content\PageReference;
use STS\Docent\DocentManager;
use STS\Docent\Search\SearchEngine;
use Throwable;

/**
 * Fluent factory for documentation test assertions. Reached via
 * {@see InteractsWithDocs::docs()}.
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
        return new PageAssertions($this->manager, $slug);
    }

    /**
     * The viewer for tree-wide assertions. Null (the default) sweeps as a guest.
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
     * Every page slug in the tree, sorted, using Docent's own derivation — the
     * root `index.md` is the empty slug, `foo/index.md` collapses to `foo`, and
     * partials are not pages. Reach for this to write suite-wide invariants
     * against the real list rather than a reconstruction of it.
     *
     * @return list<string>
     */
    public function pages(): array
    {
        $slugs = array_map(
            static fn (PageReference $page): string => $page->slug,
            [...$this->manager->repository()->all()],
        );

        sort($slugs);

        return $slugs;
    }

    /**
     * Assert every page the current viewer may open renders without throwing.
     *
     * Pages the viewer cannot see are skipped rather than failed — a sweep for a
     * narrow role is asking "does what this role can reach still work", and a
     * gated page is not in scope for that question.
     *
     * This is the invariant most applications want and almost nobody writes,
     * because assembling the page list was the tedious part.
     */
    public function assertAllPagesRender(): self
    {
        $context = $this->testContext($this->user, $this->audience);
        $failures = [];
        $rendered = 0;

        foreach ($this->pages() as $slug) {
            $page = $this->manager->page($slug);

            if ($page === null || ! $page->authorize($context)) {
                continue;
            }

            $rendered++;

            try {
                $page->render($context);
            } catch (Throwable $e) {
                $failures[] = ($slug === '' ? '(home)' : $slug).': '.$e::class.' — '.$e->getMessage();
            }
        }

        Assert::assertSame([], $failures, sprintf(
            "Expected every docs page visible to this viewer to render, but %d of %d failed:\n  %s",
            count($failures),
            $rendered,
            implode("\n  ", $failures),
        ));

        return $this;
    }

    public function search(string $query, ?Authenticatable $as = null, ?string $audience = null, int $limit = 20): SearchAssertions
    {
        $results = app(SearchEngine::class)->search($query, $this->testContext($as ?? $this->user, $audience ?? $this->audience), $limit);

        return new SearchAssertions($query, $results);
    }
}
