<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\PageReference;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Document;
use STS\Docent\Support\InternalLink;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags slug-style internal destinations that match no known page: markdown
 * links, card `href`s, and landing-page `hero.cta` hrefs all resolve through
 * the same {@see InternalLink} path the renderer uses. "Internal" mirrors the
 * HtmlRenderer's url resolver: absolute URLs, protocol-relative,
 * `mailto:`/`tel:`, and pure anchors are external and skipped; so are absolute
 * paths outside the docs route prefix. Anchor-only links are out of scope for v1.
 */
final class BrokenLinkCheck implements Check
{
    /** @var array<string, true> */
    private array $known = [];

    public function run(CheckContext $context): iterable
    {
        $this->known = $context->slugSet();

        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                $destination = match (true) {
                    $node instanceof Link && is_string($node->destination) => $node->destination,
                    $node instanceof Card => $node->href,
                    default => null,
                };

                if ($this->broken($destination, $page, $context)) {
                    yield Issue::error(
                        'broken-link',
                        $page->slug,
                        'Link to "'.$destination.'" matches no known page.',
                        $node->line,
                    );
                }
            }

            yield from $this->heroCtaIssues($document, $page, $context);
        }
    }

    /**
     * Landing-page hero CTA buttons live in front matter, not the AST, but
     * their hrefs are validated with the same semantics as any link.
     *
     * @return iterable<Issue>
     */
    private function heroCtaIssues(Document $document, PageReference $page, CheckContext $context): iterable
    {
        foreach ($document->frontMatter()->heroCta() as $cta) {
            if ($this->broken($cta['href'], $page, $context)) {
                yield Issue::error(
                    'broken-link',
                    $page->slug,
                    'Hero CTA "'.$cta['label'].'" links to "'.$cta['href'].'", which matches no known page.',
                );
            }
        }
    }

    private function broken(?string $destination, PageReference $page, CheckContext $context): bool
    {
        if ($destination === null || $destination === '') {
            return false;
        }

        $target = InternalLink::resolve($destination, $page->directory, $context->routePrefix());

        return $target !== null && ! isset($this->known[$target['slug']]);
    }
}
