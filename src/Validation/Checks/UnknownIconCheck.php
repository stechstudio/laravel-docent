<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\Card;
use STS\Docent\Support\Icon;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags `:::card icon="..."` names that are not in the built-in icon set. A
 * warning, not an error: the card still renders (without an icon), so a typo is
 * worth surfacing without failing a build.
 */
final class UnknownIconCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach (['links', 'topbar'] as $list) {
            $links = $context->docent?->config('navigation.'.$list, []) ?? [];

            if (! is_array($links)) {
                continue;
            }

            foreach ($links as $index => $link) {
                $icon = is_array($link) ? ($link['icon'] ?? null) : null;

                if (is_string($icon) && $icon !== '' && ! $this->valid($icon)) {
                    yield Issue::warning(
                        'unknown-icon',
                        'navigation.'.$list.'.'.((int) $index),
                        'Unknown navigation link icon "'.$icon.'".',
                        1,
                    );
                }
            }
        }

        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if ($node instanceof Card && $node->icon !== null && $node->icon !== '' && ! Icon::has($node->icon)) {
                    yield Issue::warning('unknown-icon', $page->slug, 'Unknown card icon "'.$node->icon.'".', $node->line);
                }
            }
        }
    }

    private function valid(string $icon): bool
    {
        return Icon::has($icon)
            || str_starts_with($icon, '/')
            || preg_match('#^https?://#i', $icon) === 1;
    }
}
