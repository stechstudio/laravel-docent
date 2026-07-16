<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\PageReference;
use STS\Docent\Content\Repositories\RedirectCollisionRepository;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/** Validates redirect aliases without resolving or disclosing them at runtime. */
final class RedirectCheck implements Check
{
    private const MAX_VALIDATION_HOPS = 20;

    private const RESERVED_PREFIXES = [
        '_search', '_ask', '_widget', '_uploads', '_insights', 'admin', 'llms.txt',
    ];

    public function run(CheckContext $context): iterable
    {
        $pages = $context->pageMap();
        $repository = $context->repository();

        if ($repository instanceof RedirectCollisionRepository) {
            foreach ($repository->redirectCollisions() as $slug) {
                yield Issue::error(
                    'redirect-collision',
                    $slug,
                    "Redirect stub collides with a real page at '{$slug}'; the real page wins.",
                );
            }
        }

        foreach ($context->pages() as $page) {
            if (! $page->redirectStub) {
                continue;
            }

            $destination = $this->destination($page);

            if ($destination === null) {
                yield Issue::error('redirect-missing', $page->slug, '`redirect` must name a destination slug.');

                continue;
            }

            if ($this->reserved($destination)) {
                yield Issue::error('redirect-reserved', $page->slug, "Redirect destination '{$destination}' collides with a reserved route prefix.");

                continue;
            }

            if (! $this->validSlug($destination)) {
                yield Issue::error('redirect-external', $page->slug, "Redirect destination '{$destination}' must be an internal Docent slug.");

                continue;
            }

            if ($destination === $page->slug) {
                yield Issue::error('redirect-self', $page->slug, 'A page cannot redirect to itself.');

                continue;
            }

            $target = $pages[$destination] ?? null;

            if ($target === null) {
                yield Issue::error('redirect-missing', $page->slug, "Redirect destination '{$destination}' does not exist.");

                continue;
            }

            if ($target->redirectStub) {
                yield Issue::warning(
                    'redirect-chain',
                    $page->slug,
                    'Redirect chain should be flattened: '.$this->chain($page, $pages).'.',
                );
            }

            if ($this->moreNarrowlyGated($page, $target)) {
                yield Issue::warning(
                    'redirect-authorization',
                    $page->slug,
                    "Destination '{$destination}' is gated more narrowly than this redirect stub; unauthorized viewers will receive a 404.",
                );
            }

            $cycle = $this->cycle($page, $pages);

            if ($cycle !== null) {
                yield Issue::error('redirect-cycle', $page->slug, 'Redirect cycle detected: '.$cycle.'.');
            }
        }
    }

    private function destination(PageReference $page): ?string
    {
        if ($page->redirect === null) {
            return null;
        }

        $destination = trim($page->redirect);

        return $destination === '' ? null : $destination;
    }

    private function validSlug(string $slug): bool
    {
        return preg_match('#^[a-z0-9]([a-z0-9/-]*[a-z0-9])?$#', $slug) === 1;
    }

    private function reserved(string $slug): bool
    {
        return in_array(explode('/', $slug, 2)[0], self::RESERVED_PREFIXES, true);
    }

    /** @param array<string, PageReference> $pages */
    private function chain(PageReference $start, array $pages): string
    {
        $chain = [$start->slug];
        $current = $start;

        for ($hop = 0; $hop < self::MAX_VALIDATION_HOPS; $hop++) {
            $destination = $this->destination($current);

            if ($destination === null) {
                break;
            }

            $chain[] = $destination;
            $current = $pages[$destination] ?? null;

            if ($current === null || ! $current->redirectStub || in_array($destination, array_slice($chain, 0, -1), true)) {
                break;
            }
        }

        return implode(' -> ', $chain);
    }

    /** @param array<string, PageReference> $pages */
    private function cycle(PageReference $start, array $pages): ?string
    {
        $path = [$start->slug];
        $seen = [$start->slug => 0];
        $current = $start;

        for ($hop = 0; $hop < self::MAX_VALIDATION_HOPS; $hop++) {
            $destination = $this->destination($current);

            if ($destination === null || ! isset($pages[$destination]) || ! $pages[$destination]->redirectStub) {
                return null;
            }

            if (isset($seen[$destination])) {
                return implode(' -> ', [...array_slice($path, $seen[$destination]), $destination]);
            }

            $seen[$destination] = count($path);
            $path[] = $destination;
            $current = $pages[$destination];
        }

        return null;
    }

    private function moreNarrowlyGated(PageReference $stub, PageReference $target): bool
    {
        return ($target->authorize !== null && $target->authorize !== $stub->authorize)
            || ($target->audience !== null && $target->audience !== $stub->audience);
    }
}
