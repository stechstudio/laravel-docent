<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\PageReference;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\AuthorizationMode;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Support\InternalLink;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;
use STS\Docent\Validation\OptInCheck;

/**
 * Flags links from an ungated page to a gated one — a dead end for every reader
 * whose role lacks the target's requirement.
 *
 * {@see BrokenLinkCheck} validates that a target exists; this asks whether the
 * readers of the linking page can actually open it. The failure surfaces late,
 * because the author can see both pages and CI is green: the only signal is a
 * user with a narrower role reporting a link that goes nowhere. The worst case
 * is a deliberately ungated "roles and permissions" page, where the tooling
 * hands a 404 to precisely the audience least equipped to interpret it.
 *
 * The rule is deliberately narrow. Abilities are not a lattice, so a page gated
 * on `manage_billing` linking to one gated on `manage_users` says nothing about
 * whether a reader holds both — that is a legitimate cross-link as often as not.
 * Only a source with no requirement at all is provably reaching a wider audience
 * than a gated target, so only that case is reported.
 *
 * A link nested inside a `:::can` or `:::audience` block is already gated in
 * context — its readers passed that check to see it — and is exempt. That is
 * also the escape hatch for a deliberate "here's the page you'll need an
 * administrator for". A `:::cannot` block widens rather than narrows, so links
 * inside one are still reported.
 *
 * Opt in with `'gated-link' => 'warning'` in `docent.check.rules`.
 */
final class GatedLinkCheck implements OptInCheck
{
    public function rule(): string
    {
        return 'gated-link';
    }

    public function run(CheckContext $context): iterable
    {
        $pages = $context->pageMap();

        foreach ($context->pages() as $page) {
            // A gated source has requirements of its own, and comparing two
            // different requirements is not statically decidable.
            if ($page->authorize !== null || $page->audience !== null) {
                continue;
            }

            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            yield from $this->scan($document, $page, $pages, $context, gated: false);

            foreach ($document->frontMatter()->heroCta() as $cta) {
                yield from $this->issuesFor($cta['href'], null, $page, $pages, $context);
            }
        }
    }

    /**
     * Walk the tree carrying whether the current subtree already sits behind a
     * narrowing gate.
     *
     * @param  array<string, PageReference>  $pages
     * @return iterable<Issue>
     */
    private function scan(Node $node, PageReference $page, array $pages, CheckContext $context, bool $gated): iterable
    {
        $gated = $gated || $this->narrows($node);

        if (! $gated) {
            $destination = match (true) {
                $node instanceof Link && is_string($node->destination) => $node->destination,
                $node instanceof Card => $node->href,
                default => null,
            };

            yield from $this->issuesFor($destination, $node->line, $page, $pages, $context);
        }

        foreach ($node->children as $child) {
            yield from $this->scan($child, $page, $pages, $context, $gated);
        }
    }

    /**
     * Whether this block narrows its readership. `:::cannot` shows content to
     * viewers who FAIL the gate, so it widens and does not count.
     */
    private function narrows(Node $node): bool
    {
        return $node instanceof AudienceBlock
            || ($node instanceof AuthorizationBlock && $node->mode === AuthorizationMode::Can);
    }

    /**
     * @param  array<string, PageReference>  $pages
     * @return iterable<Issue>
     */
    private function issuesFor(?string $destination, ?int $line, PageReference $page, array $pages, CheckContext $context): iterable
    {
        if ($destination === null || $destination === '') {
            return;
        }

        $target = InternalLink::resolve($destination, $page->directory, $context->routePrefix());
        $reference = $target === null ? null : ($pages[$target['slug']] ?? null);

        if ($reference === null) {
            return;
        }

        $requirement = match (true) {
            $reference->authorize !== null => 'the ability "'.$reference->authorize.'"',
            $reference->audience !== null => 'the audience "'.$reference->audience.'"',
            default => null,
        };

        if ($requirement === null) {
            return;
        }

        yield Issue::warning(
            $this->rule(),
            $page->slug,
            'Link to "'.$destination.'" requires '.$requirement.', which readers of this ungated page may not have.',
            $line,
        );
    }
}
