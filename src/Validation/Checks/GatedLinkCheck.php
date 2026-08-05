<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\PageReference;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\AuthorizationMode;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Document;
use STS\Docent\Support\InternalLink;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;
use STS\Docent\Validation\OptInCheck;

/**
 * Flags links whose readers are not guaranteed to be able to open the target —
 * a dead end for everyone the target's gate turns away.
 *
 * {@see BrokenLinkCheck} validates that a target exists; this asks whether the
 * readers who can see the link can follow it. The failure surfaces late,
 * because the author can see both pages and CI is green: the only signal is a
 * user with a narrower role reporting a link that goes nowhere. The worst case
 * is a deliberately ungated "roles and permissions" page, where the tooling
 * hands a 404 to precisely the audience least equipped to interpret it.
 *
 * What makes this decidable without inventing a permission lattice is exact
 * containment. Each link carries the set of requirements its readers provably
 * satisfy: the source page's own `authorize`/`audience`, plus any enclosing
 * `:::can` / `:::audience` block. A target requirement absent from that set is
 * reported — Docent cannot know whether holding `manage_billing` implies
 * `manage_users`, and guessing either way would be wrong.
 *
 * That makes the escape hatch a statement rather than a trick: wrapping a
 * deliberate link in a block naming the target's own requirement declares the
 * guarantee, and the check believes it. `:::cannot` widens rather than narrows,
 * and `:::when` / `:::unless` gate on conditions rather than authorization, so
 * neither contributes a guarantee.
 *
 * Opt in with `'gated-link' => 'warning'` in `docent.check.rules`.
 */
final class GatedLinkCheck implements OptInCheck
{
    /** @var array<string, PageReference> */
    private array $pages = [];

    public function rule(): string
    {
        return 'gated-link';
    }

    public function run(CheckContext $context): iterable
    {
        $this->pages = $context->pageMap();

        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            // A page's own gate is a guarantee about everyone reading it.
            $guaranteed = [
                'abilities' => $page->authorize !== null ? [$page->authorize] : [],
                'audiences' => $page->audience !== null ? [$page->audience] : [],
            ];

            yield from $this->scan($document, $page, $context, $guaranteed, []);

            foreach ($document->frontMatter()->heroCta() as $cta) {
                yield from $this->issuesFor(
                    $cta['href'],
                    null,
                    'Hero CTA "'.$cta['label'].'" links',
                    $page,
                    $context,
                    $guaranteed,
                );
            }
        }
    }

    /**
     * @param  array{abilities: list<string>, audiences: list<string>}  $guaranteed
     * @param  list<string>  $includes  Partial names already open, so a cycle terminates.
     * @return iterable<Issue>
     */
    private function scan(Node $node, PageReference $page, CheckContext $context, array $guaranteed, array $includes, ?int $includeLine = null, string $via = ''): iterable
    {
        $guaranteed = $this->withGuarantee($node, $guaranteed);

        $destination = match (true) {
            $node instanceof Link && is_string($node->destination) => $node->destination,
            $node instanceof Card => $node->href,
            default => null,
        };

        yield from $this->issuesFor(
            $destination,
            $includeLine ?? $node->line,
            $via === '' ? 'Link' : 'Link in partial "'.$via.'"',
            $page,
            $context,
            $guaranteed,
        );

        // A partial is rendered into this page, so its links are this page's
        // dead ends — reported against the including line, since a line number
        // inside the partial would point at the wrong file.
        if ($node instanceof IncludeNode && ! in_array($node->name, $includes, true)) {
            $partial = $context->partial($node->name);

            if ($partial instanceof Document) {
                yield from $this->scan($partial, $page, $context, $guaranteed, [...$includes, $node->name], $node->line, $node->name);
            }
        }

        foreach ($node->children as $child) {
            yield from $this->scan($child, $page, $context, $guaranteed, $includes, $includeLine, $via);
        }
    }

    /**
     * Add what this block guarantees about its readers. Only `:::can` and
     * `:::audience` narrow readership to a stated requirement.
     *
     * @param  array{abilities: list<string>, audiences: list<string>}  $guaranteed
     * @return array{abilities: list<string>, audiences: list<string>}
     */
    private function withGuarantee(Node $node, array $guaranteed): array
    {
        if ($node instanceof AuthorizationBlock && $node->mode === AuthorizationMode::Can && $node->ability !== '') {
            $guaranteed['abilities'][] = $node->ability;
        }

        if ($node instanceof AudienceBlock && $node->audience !== '') {
            $guaranteed['audiences'][] = $node->audience;
        }

        return $guaranteed;
    }

    /**
     * @param  array{abilities: list<string>, audiences: list<string>}  $guaranteed
     * @return iterable<Issue>
     */
    private function issuesFor(?string $destination, ?int $line, string $subject, PageReference $page, CheckContext $context, array $guaranteed): iterable
    {
        if ($destination === null || $destination === '') {
            return;
        }

        $target = InternalLink::resolve($destination, $page->directory, $context->routePrefix());
        $reference = $target === null ? null : ($this->pages[$target['slug']] ?? null);

        if ($reference === null) {
            return;
        }

        $missing = [];

        if ($reference->authorize !== null && ! in_array($reference->authorize, $guaranteed['abilities'], true)) {
            $missing[] = 'the ability "'.$reference->authorize.'"';
        }

        if ($reference->audience !== null && ! in_array($reference->audience, $guaranteed['audiences'], true)) {
            $missing[] = 'the audience "'.$reference->audience.'"';
        }

        if ($missing === []) {
            return;
        }

        yield Issue::warning(
            $this->rule(),
            $page->slug,
            $subject.' to "'.$destination.'" requires '.implode(' and ', $missing)
            .', which readers here are not guaranteed to have. '
            .'Wrap it in a block naming that requirement when the link is deliberate.',
            $line,
        );
    }
}
