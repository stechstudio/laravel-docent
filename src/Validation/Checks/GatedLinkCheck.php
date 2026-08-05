<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\PageReference;
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
use STS\Docent\Validation\ReaderGuarantees;

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

    private CheckContext $context;

    public function rule(): string
    {
        return 'gated-link';
    }

    public function run(CheckContext $context): iterable
    {
        $this->context = $context;
        $this->pages = $context->pageMap();

        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            $guaranteed = ReaderGuarantees::forPage($page);

            yield from $this->scan($document, $page, $guaranteed, []);

            foreach ($document->frontMatter()->heroCta() as $cta) {
                yield from $this->issuesFor($cta['href'], null, 'Hero CTA "'.$cta['label'].'" links', $page, $guaranteed);
            }
        }
    }

    /**
     * @param  list<string>  $includes  Partial names already open, so a cycle terminates.
     * @return iterable<Issue>
     */
    private function scan(Node $node, PageReference $page, ReaderGuarantees $guaranteed, array $includes, ?int $includeLine = null): iterable
    {
        $guaranteed = $guaranteed->within($node);

        $destination = match (true) {
            $node instanceof Link && is_string($node->destination) => $node->destination,
            $node instanceof Card => $node->href,
            default => null,
        };

        yield from $this->issuesFor($destination, $includeLine ?? $node->line, $this->subject($includes), $page, $guaranteed);

        // A partial is rendered into this page, so its links are this page's
        // dead ends — reported against the including line, since a line number
        // inside the partial would point at the wrong file.
        if ($node instanceof IncludeNode && ! in_array($node->name, $includes, true)) {
            $partial = $this->context->partial($node->name);

            if ($partial instanceof Document) {
                yield from $this->scan($partial, $page, $guaranteed, [...$includes, $node->name], $node->line);
            }
        }

        foreach ($node->children as $child) {
            yield from $this->scan($child, $page, $guaranteed, $includes, $includeLine);
        }
    }

    /**
     * How to name the thing carrying the link, so a reader of the report knows
     * which file to open.
     *
     * @param  list<string>  $includes
     */
    private function subject(array $includes): string
    {
        return $includes === []
            ? 'Link'
            : 'Link in partial "'.$includes[array_key_last($includes)].'"';
    }

    /**
     * @return iterable<Issue>
     */
    private function issuesFor(?string $destination, ?int $line, string $subject, PageReference $page, ReaderGuarantees $guaranteed): iterable
    {
        if ($destination === null || $destination === '') {
            return;
        }

        $target = InternalLink::resolve($destination, $page->directory, $this->context->routePrefix());
        $reference = $target === null ? null : ($this->pages[$target['slug']] ?? null);

        if ($reference === null) {
            return;
        }

        $missing = $guaranteed->shortfallFor($reference);

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
