<?php

declare(strict_types=1);

namespace STS\Docent\Content;

use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Renderer\AgentMarkdownRenderer;
use STS\Docent\Navigation\NavigationBuilder;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Navigation\NavigationItem;
use STS\Docent\Page;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\DocentCache;

final class AgentFeed
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly DocumentationRepository $repository,
        private readonly DocentCache $cache,
        private readonly IntegrationRegistry $registry,
        private readonly NavigationBuilder $navigation,
    ) {}

    /**
     * Render one page for agent-facing HTTP surfaces. The viewer fingerprint
     * isolates cached output for different navigation scopes and users.
     */
    public function agentMarkdown(Page $page, DocumentationContext $context): string
    {
        $key = implode(':', [
            'agent-page',
            $this->repository->directoryHash(),
            $this->docent->viewerFingerprint($context),
            sha1($page->slug),
        ]);

        return $this->cache->remember($key, function () use ($page, $context): string {
            $renderer = new AgentMarkdownRenderer(
                registry: $this->registry,
                context: $context,
                baseDir: $page->baseDir(),
                routePrefix: (string) $this->docent->config('route.prefix', 'docs'),
                includeResolver: fn (string $name): ?Document => $this->docent->partialDocument($name),
                markdownUrlResolver: fn (string $slug): string => $this->docent->markdownUrl($slug),
                sectionCardsResolver: fn (string $section): array => $this->docent->sectionCards($section, $context),
            );

            return $renderer->render($page->document(), $page->title(), $page->description());
        });
    }

    public function discoveryLinkHeader(): string
    {
        $index = parse_url($this->docent->llmsUrl(), PHP_URL_PATH) ?: '/llms.txt';
        $full = parse_url($this->docent->llmsUrl(true), PHP_URL_PATH) ?: '/llms-full.txt';

        return '<'.$index.'>; rel="llms-txt", <'.$full.'>; rel="llms-full-txt"';
    }

    public function llmsText(DocumentationContext $context): string
    {
        $navigationSections = $this->docent->navigationSections($context);
        $key = 'llms:'.$this->repository->directoryHash().':'.$this->docent->viewerFingerprint($context);

        return $this->cache->remember($key, function () use ($navigationSections): string {
            $sections = [];

            // Within each section, ungrouped pages sit under the section's own
            // heading and every top-level group keeps its own — a sectionless
            // site reads exactly as it did before sections existed.
            foreach ($navigationSections as $section) {
                $root = array_values(array_filter(
                    $section->navigation,
                    static fn (object $node): bool => $node instanceof NavigationItem,
                ));

                if ($root !== []) {
                    $sections[] = $this->llmsSection($section->label, $root);
                }

                foreach ($section->navigation as $node) {
                    if ($node instanceof NavigationGroup) {
                        $sections[] = $this->llmsSection($node->label, $this->navigation->flatten([$node]));
                    }
                }
            }

            return '# '.$this->docent->siteName()."\n\n> ".$this->docent->siteDescription()."\n"
                .($sections === [] ? '' : "\n".implode("\n\n", $sections)."\n");
        });
    }

    public function llmsFullText(DocumentationContext $context): string
    {
        $navigationSections = $this->docent->navigationSections($context);
        $key = 'llms-full:'.$this->repository->directoryHash().':'.$this->docent->viewerFingerprint($context);

        return $this->cache->remember($key, function () use ($navigationSections, $context): string {
            $pages = [];

            foreach ($navigationSections as $section) {
                foreach ($this->navigation->flatten($section->navigation) as $item) {
                    if ($item->searchExcluded) {
                        continue;
                    }

                    $page = $this->docent->page($item->slug);

                    if ($page !== null && $page->authorize($context)) {
                        $pages[] = trim($this->agentMarkdown($page, $context));
                    }
                }
            }

            return $pages === [] ? '' : implode("\n\n---\n\n", $pages)."\n";
        });
    }

    /** @param list<NavigationItem> $items */
    private function llmsSection(string $label, array $items): string
    {
        $lines = ['## '.$label];

        foreach ($items as $item) {
            $line = '- ['.$item->title.']('.$this->docent->markdownUrl($item->slug).')';
            $description = preg_replace('/\s+/', ' ', trim((string) $item->description));
            $lines[] = $line.($description === '' ? '' : ': '.$description);
        }

        return implode("\n\n", $lines);
    }
}
