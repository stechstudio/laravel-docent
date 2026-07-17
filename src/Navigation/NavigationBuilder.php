<?php

declare(strict_types=1);

namespace STS\Docent\Navigation;

use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use STS\Docent\Content\PageReference;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\DocentCache;
use STS\Docent\Support\Icon;

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
     * Partition the global tree into the default documentation area and each
     * viewer-visible promoted top-level directory.
     *
     * @return list<NavigationSection>
     */
    public function sections(DocumentationContext $context, string $currentSlug = ''): array
    {
        $skeleton = $this->skeleton();
        $defaultChildren = [];
        $promoted = [];

        foreach ($skeleton['children'] as $segment => $node) {
            if (($node['section'] ?? false) === true) {
                $promoted[$segment] = $node;
            } else {
                $defaultChildren[$segment] = $node;
            }
        }

        $sections = [];
        $defaultNavigation = $this->filterLevel([
            'items' => $skeleton['items'],
            'children' => $defaultChildren,
        ], $context);

        if ($defaultNavigation !== []) {
            $sections[] = $this->section(
                (string) config('docent.navigation.default_section', 'Documentation'),
                null,
                $defaultNavigation,
            );
        }

        foreach ($this->sortGroups($promoted) as $node) {
            $navigation = $this->filterLevel($node, $context);

            if ($navigation !== []) {
                $sections[] = $this->section($node['label'], $node['directory'], $navigation);
            }
        }

        $activeDirectory = null;

        foreach ($sections as $section) {
            if ($section->directory !== null
                && ($currentSlug === $section->directory || str_starts_with($currentSlug, $section->directory.'/'))
                && ($activeDirectory === null || strlen($section->directory) > strlen($activeDirectory))) {
                $activeDirectory = $section->directory;
            }
        }

        return array_map(
            static fn (NavigationSection $section): NavigationSection => new NavigationSection(
                $section->label,
                $section->directory,
                $section->url,
                $section->navigation,
                $activeDirectory === null ? $section->directory === null : $section->directory === $activeDirectory,
            ),
            $sections,
        );
    }

    /**
     * @return list<NavigationItem|NavigationGroup>
     */
    public function sectionNavigation(string $slug, DocumentationContext $context): array
    {
        foreach ($this->sections($context, $slug) as $section) {
            if ($section->active) {
                return $section->navigation;
            }
        }

        return [];
    }

    /**
     * Resolve configured persistent sidebar links for the current viewer.
     *
     * @return list<NavigationLink>
     */
    public function links(DocumentationContext $context, string $currentSlug = ''): array
    {
        return $this->resolveLinks(config('docent.navigation.links', []), $context, $currentSlug);
    }

    /**
     * Resolve configured top-bar utility links (GitHub, Discord, a status
     * page) for the current viewer. Same entry shape as {@see links()},
     * different placement.
     *
     * @return list<NavigationLink>
     */
    public function topbarLinks(DocumentationContext $context, string $currentSlug = ''): array
    {
        return $this->resolveLinks(config('docent.navigation.topbar', []), $context, $currentSlug);
    }

    /**
     * @return list<NavigationLink>
     */
    private function resolveLinks(mixed $configured, DocumentationContext $context, string $currentSlug): array
    {
        if (! is_array($configured) || $configured === []) {
            return [];
        }

        // Built on first `page` target only: resolving page visibility means
        // sweeping the repository, which most configurations never need.
        $pages = null;

        $links = [];

        foreach ($configured as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $label = trim((string) ($definition['label'] ?? ''));
            $targets = array_values(array_filter(
                ['url', 'page', 'route'],
                static fn (string $target): bool => isset($definition[$target]) && is_string($definition[$target]) && trim($definition[$target]) !== '',
            ));

            if ($label === '' || count($targets) !== 1) {
                continue;
            }

            $ability = $definition['can'] ?? null;

            if (is_string($ability) && trim($ability) !== '' && ! $context->can(trim($ability))) {
                continue;
            }

            $target = $targets[0];
            $value = trim($definition[$target]);
            $external = false;
            $active = false;

            if ($target === 'page') {
                $pages ??= $this->pageMap();
                $page = $pages[$value] ?? null;

                if ($page === null || ! $this->visible($page, $context)) {
                    continue;
                }

                $url = ($this->urlResolver)($value);
                $active = $currentSlug === $value;
            } elseif ($target === 'route') {
                if (! Route::has($value)) {
                    continue;
                }

                $url = route($value);
            } else {
                $url = $value;
                $external = preg_match('#^(?:(?:https?:)?//|[a-z][a-z0-9+.-]*:)#i', $url) === 1;
            }

            [$icon, $iconIsImage] = $this->linkIcon($definition['icon'] ?? null);
            $links[] = new NavigationLink($label, $url, $icon, $iconIsImage, $external, $active);
        }

        return $links;
    }

    /**
     * Card summaries for the `::section-cards` directive and the
     * `x-docent::section-cards` component: every top-level directory when
     * `$section` is empty, or the children (pages and sub-directories) of
     * one directory. Cards honor the same per-viewer filtering as the
     * sidebar, so the grid adapts to who is looking at it.
     *
     * @return list<SectionCard>
     */
    public function cards(string $section, DocumentationContext $context): array
    {
        $section = trim($section, '/');
        $level = $this->level($section);

        if ($level === null) {
            return [];
        }

        $cards = [];

        // A named directory's own pages become cards; its index page
        // describes the directory itself rather than appearing as a card.
        // Loose root pages (the homepage among them) never become cards.
        if ($section !== '') {
            foreach ($this->sortPages($level['items']) as $page) {
                if ($page->slug !== $section && $this->visible($page, $context)) {
                    $cards[] = new SectionCard($page->slug, $page->title, ($this->urlResolver)($page->slug), $page->description);
                }
            }
        }

        foreach ($this->sortGroups($level['children']) as $node) {
            $card = $this->groupCard($node, $context);

            if ($card !== null) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * @return array{items: list<PageReference>, children: array<string, mixed>}|null
     */
    private function level(string $section): ?array
    {
        $level = $this->skeleton();

        if ($section === '') {
            return $level;
        }

        foreach (explode('/', $section) as $segment) {
            $level = $level['children'][$segment] ?? null;

            if ($level === null) {
                return null;
            }
        }

        return $level;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function groupCard(array $node, DocumentationContext $context): ?SectionCard
    {
        $group = $this->filterGroup($node, $context);

        if ($group === null) {
            return null;
        }

        $pages = $this->flatten([$group]);
        $index = null;

        foreach ($group->items as $item) {
            if ($item->slug === $node['directory']) {
                $index = $item;
                break;
            }
        }

        return new SectionCard(
            $node['directory'],
            $node['label'],
            $index?->url ?? $pages[0]->url,
            ($node['description'] ?? null) ?? $index?->description,
            $node['icon'],
            count($pages),
        );
    }

    /**
     * @return array{0: ?NavigationItem, 1: ?NavigationItem}
     */
    public function prevNext(string $slug, DocumentationContext $context): array
    {
        $flat = $this->flatten($this->sectionNavigation($slug, $context));

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
        /** @var array{items: list<PageReference>, children: array<string, mixed>} $tree */
        $tree = ['items' => [], 'children' => []];

        foreach ($this->repository->all() as $page) {
            // Landing and custom-layout pages stay out of the sidebar: they
            // are jump-off points reached through the logo or links, not
            // stops along a section. Search and direct URLs are unaffected;
            // list such a page by giving it the default docs layout.
            if ($page->hidden || $page->layout !== 'docs') {
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
                        'description' => $meta['description'] ?? null,
                        'order' => $meta['order'] ?? null,
                        'directory' => $accumulated,
                        'section' => ($meta['section'] ?? false) === true,
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
        return new NavigationItem(
            $page->title,
            $page->slug,
            ($this->urlResolver)($page->slug),
            $page->description,
            $page->searchExcluded,
        );
    }

    /** @return array<string, PageReference> */
    private function pageMap(): array
    {
        $pages = [];

        foreach ($this->repository->all() as $page) {
            $pages[$page->slug] = $page;
        }

        return $pages;
    }

    /**
     * @param  list<NavigationItem|NavigationGroup>  $navigation
     */
    private function section(string $label, ?string $directory, array $navigation): NavigationSection
    {
        return new NavigationSection(
            $label,
            $directory,
            $this->flatten($navigation)[0]->url,
            $navigation,
        );
    }

    /** @return array{0: ?string, 1: bool} */
    private function linkIcon(mixed $icon): array
    {
        if (! is_string($icon) || trim($icon) === '') {
            return [null, false];
        }

        $icon = trim($icon);

        if (Icon::has($icon)) {
            return [$icon, false];
        }

        if (str_starts_with($icon, '/') || preg_match('#^https?://#i', $icon) === 1) {
            return [$icon, true];
        }

        return [null, false];
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
