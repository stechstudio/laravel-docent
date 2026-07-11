<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Assert;
use STS\Docent\DocentManager;
use STS\Docent\Page;

/**
 * Fluent assertions about a single documentation page rendered for a given
 * viewer, driving the real manager/renderer pipeline (not HTTP).
 */
final class PageAssertions
{
    use BuildsTestContext;

    private ?Authenticatable $user = null;

    private ?string $audience = null;

    public function __construct(
        private readonly DocentManager $manager,
        private readonly string $slug,
    ) {}

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
     * Assert the current viewer may open the page (front-matter `authorize` and
     * `audience` both pass).
     */
    public function assertVisible(): self
    {
        Assert::assertTrue(
            $this->page()->authorize($this->testContext($this->user, $this->audience)),
            "Expected docs page [{$this->slug}] to be visible to the viewer, but authorization denied it.",
        );

        return $this;
    }

    public function assertNotVisible(): self
    {
        Assert::assertFalse(
            $this->page()->authorize($this->testContext($this->user, $this->audience)),
            "Expected docs page [{$this->slug}] to be hidden from the viewer, but authorization allowed it.",
        );

        return $this;
    }

    public function assertSee(string $text): self
    {
        Assert::assertTrue(
            $this->contains($text),
            "Expected to see [{$text}] on docs page [{$this->slug}] for this viewer, but it was not rendered.",
        );

        return $this;
    }

    public function assertDontSee(string $text): self
    {
        Assert::assertFalse(
            $this->contains($text),
            "Expected NOT to see [{$text}] on docs page [{$this->slug}] for this viewer, but it was rendered.",
        );

        return $this;
    }

    private function contains(string $needle): bool
    {
        $html = $this->page()->render($this->testContext($this->user, $this->audience));
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))) ?? '');

        return str_contains($html, $needle) || str_contains($text, $needle);
    }

    private function page(): Page
    {
        $page = $this->manager->page($this->slug);

        Assert::assertNotNull($page, "Docs page [{$this->slug}] does not exist.");

        return $page;
    }
}
