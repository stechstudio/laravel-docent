<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

use STS\Docent\Content\PageReference;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\AuthorizationMode;
use STS\Docent\Documents\Ast\Node;

/**
 * What a link's readers provably satisfy: the abilities and audiences every one
 * of them must already have passed to be looking at it.
 *
 * This is what makes reader visibility statically decidable without inventing a
 * permission lattice. Docent cannot know whether holding `manage_billing`
 * implies `manage_users`, so it only ever asks whether a requirement is present
 * — never whether one requirement implies another.
 *
 * @internal
 */
final class ReaderGuarantees
{
    /**
     * @param  list<string>  $abilities
     * @param  list<string>  $audiences
     */
    private function __construct(
        private readonly array $abilities,
        private readonly array $audiences,
    ) {}

    /**
     * A page's own gate is a guarantee about everyone reading it.
     */
    public static function forPage(PageReference $page): self
    {
        return new self(
            $page->authorize !== null ? [$page->authorize] : [],
            $page->audience !== null ? [$page->audience] : [],
        );
    }

    /**
     * These guarantees plus whatever the given block adds. Only `:::can` and
     * `:::audience` narrow readership to a stated requirement — `:::cannot`
     * widens rather than narrows, and `:::when` / `:::unless` gate on
     * conditions rather than authorization, so neither adds anything.
     */
    public function within(Node $node): self
    {
        return match (true) {
            $node instanceof AuthorizationBlock && $node->mode === AuthorizationMode::Can && $node->ability !== '' => new self(
                [...$this->abilities, $node->ability],
                $this->audiences,
            ),
            $node instanceof AudienceBlock && $node->audience !== '' => new self(
                $this->abilities,
                [...$this->audiences, $node->audience],
            ),
            default => $this,
        };
    }

    /**
     * The target's requirements these guarantees do not cover, phrased for a
     * diagnostic. Empty when a reader who can see the link can open the target.
     *
     * @return list<string>
     */
    public function shortfallFor(PageReference $target): array
    {
        $missing = [];

        if ($target->authorize !== null && ! in_array($target->authorize, $this->abilities, true)) {
            $missing[] = 'the ability "'.$target->authorize.'"';
        }

        if ($target->audience !== null && ! in_array($target->audience, $this->audiences, true)) {
            $missing[] = 'the audience "'.$target->audience.'"';
        }

        return $missing;
    }
}
