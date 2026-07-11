<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

use Closure;
use Illuminate\Support\Str;
use STS\Docent\Content\PageReference;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\DocentCache;

/**
 * Builds the sidebar navigation in two stages: a globally cacheable skeleton
 * (directories → groups, ordered pages, `_group.yml` metadata) keyed by the
 * repository's directory hash, and a per-request {@see filtered()} pass that
 * applies page authorization/audience visibility.
 */
final class NavigationBuilder
{
    /**
     * @param  Closure(string): string  $urlResolver
     */
    public function __construct(
        private readonly DocumentationRepository $repository,
        private readonly IntegrationRegistry $registry,
        private readonly DocentCache $cache,
        private readonly Closure $urlResolver,
    ) {}

    /**
     * @return list<NavigationItem|NavigationGroup>
     */
    public function filtered(DocumentationContext $context): array
    {
        return $this->filterLevel($this->skeleton(), $context);
    }

    /**
     * @return array{0: ?NavigationItem, 1: ?NavigationItem}
     */
    public function prevNext(string $slug, DocumentationContext $context): array
    {
        $flat = $this->flatten($this->filtered($context));

        foreach ($flat as $index => $item) {
            if ($item->slug === $slug) {
                return [$flat[$index - 1] ?? null, $flat[$index + 1] ?? null];
            }
        }

        return [null, null];
    }

    /**
     * The cached, pre-authorization tree. Hidden pages are already excluded
     * (hidden is not context-dependent).
     *
     * @return array{items: list<PageReference>, children: array<string, mixed>}
     */
    private function skeleton(): array
    {
        return $this->cache->remember('nav:'.$this->repository->directoryHash(), fn (): array => $this->build());
    }

    /**
     * @return array{items: list<PageReference>, children: array<string, mixed>}
     */
    private function build(): array
    {
        $tree = ['items' => [], 'children' => []];

        foreach ($this->repository->all() as $page) {
            if ($page->hidden) {
                continue;
            }

            if ($page->directory === '') {
                $tree['items'][] = $page;

                continue;
            }

            $cursor = &$tree;
            $accumulated = '';

            foreach (explode('/', $page->directory) as $segment) {
                $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;

                if (! isset($cursor['children'][$segment])) {
                    $meta = $this->repository->groupMeta($accumulated) ?? [];

                    $cursor['children'][$segment] = [
                        'label' => $meta['label'] ?? Str::headline($segment),
                        'icon' => $meta['icon'] ?? null,
                        'order' => $meta['order'] ?? null,
                        'items' => [],
                        'children' => [],
                    ];
                }

                $cursor = &$cursor['children'][$segment];
            }

            $cursor['items'][] = $page;
            unset($cursor);
        }

        return $tree;
    }

    /**
     * @param  array{items: list<PageReference>, children: array<string, mixed>}  $level
     * @return list<NavigationItem|NavigationGroup>
     */
    private function filterLevel(array $level, DocumentationContext $context): array
    {
        $result = [];

        foreach ($this->sortPages($level['items']) as $page) {
            if ($this->visible($page, $context)) {
                $result[] = $this->item($page);
            }
        }

        foreach ($this->sortGroups($level['children']) as $node) {
            $group = $this->filterGroup($node, $context);

            if ($group !== null) {
                $result[] = $group;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function filterGroup(array $node, DocumentationContext $context): ?NavigationGroup
    {
        $items = [];

        foreach ($this->sortPages($node['items']) as $page) {
            if ($this->visible($page, $context)) {
                $items[] = $this->item($page);
            }
        }

        $groups = [];

        foreach ($this->sortGroups($node['children']) as $child) {
            $group = $this->filterGroup($child, $context);

            if ($group !== null) {
                $groups[] = $group;
            }
        }

        if ($items === [] && $groups === []) {
            return null;
        }

        return new NavigationGroup($node['label'], $node['icon'], $items, $groups);
    }

    private function visible(PageReference $page, DocumentationContext $context): bool
    {
        if ($page->authorize !== null && ! $context->can($page->authorize)) {
            return false;
        }

        if ($page->audience !== null && ! $this->audienceAllows($page->audience, $context)) {
            return false;
        }

        return true;
    }

    private function audienceAllows(string $audience, DocumentationContext $context): bool
    {
        if ($context->audience !== null) {
            return $context->audience === $audience;
        }

        return $this->registry->resolveAudience($audience, $context) ?? false;
    }

    private function item(PageReference $page): NavigationItem
    {
        return new NavigationItem($page->title, $page->slug, ($this->urlResolver)($page->slug));
    }

    /**
     * @param  list<PageReference>  $pages
     * @return list<PageReference>
     */
    private function sortPages(array $pages): array
    {
        usort($pages, fn (PageReference $a, PageReference $b): int => [$a->order ?? PHP_INT_MAX, $a->title] <=> [$b->order ?? PHP_INT_MAX, $b->title]);

        return $pages;
    }

    /**
     * @param  array<string, mixed>  $children
     * @return list<array<string, mixed>>
     */
    private function sortGroups(array $children): array
    {
        $groups = array_values($children);

        usort($groups, fn (array $a, array $b): int => [$a['order'] ?? PHP_INT_MAX, $a['label']] <=> [$b['order'] ?? PHP_INT_MAX, $b['label']]);

        return $groups;
    }

    /**
     * @param  list<NavigationItem|NavigationGroup>  $nodes
     * @return list<NavigationItem>
     */
    private function flatten(array $nodes): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            if ($node instanceof NavigationItem) {
                $flat[] = $node;

                continue;
            }

            $flat = array_merge($flat, $node->items, $this->flatten($node->groups));
        }

        return $flat;
    }
}
