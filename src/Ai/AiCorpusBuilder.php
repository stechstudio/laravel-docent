<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\DocentManager;
use STS\Docent\Navigation\NavigationGroup;
use STS\Docent\Navigation\NavigationItem;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Support\DocentCache;

final class AiCorpusBuilder
{
    public function __construct(
        private readonly DocentManager $docent,
        private readonly DocumentationRepository $repository,
        private readonly DocentCache $cache,
    ) {}

    public function build(DocumentationContext $context, bool $widget = false): AiCorpus
    {
        $budget = max(1, (int) config('docent.ai.corpus_budget', 150000));
        $fingerprint = $this->docent->viewerFingerprint($context);
        $mode = $widget ? 'widget' : 'reader';
        $key = implode(':', ['ai-corpus', $this->repository->directoryHash(), $fingerprint, $mode, $budget]);

        return $this->cache->remember($key, fn (): AiCorpus => $this->assemble($context, $widget, $budget, $fingerprint));
    }

    private function assemble(DocumentationContext $context, bool $widget, int $budget, string $fingerprint): AiCorpus
    {
        $pages = [];

        foreach ($this->docent->navigationSections($context) as $section) {
            foreach ($this->flatten($section->navigation) as $item) {
                if ($item->searchExcluded) {
                    continue;
                }

                $page = $this->docent->page($item->slug);

                if ($page === null || ! $page->authorize($context)) {
                    continue;
                }

                $url = $widget ? $this->docent->widgetUrl($item->slug) : $this->docent->fullUrl($item->slug);
                $pages[] = [
                    'slug' => $item->slug,
                    'title' => $item->title,
                    'url' => $url,
                    'content' => $this->pageBlock(
                        $item->title,
                        $url,
                        trim($this->docent->agentMarkdown($page, $context)),
                    ),
                ];
            }
        }

        $limit = $budget * 4;
        $included = [];
        $characters = 0;

        foreach ($pages as $page) {
            $separator = $included === [] ? '' : "\n\n";
            $next = strlen($separator.$page['content']);

            if ($characters + $next > $limit) {
                break;
            }

            $included[] = $page;
            $characters += $next;
        }

        $omitted = count($pages) - count($included);
        $truncated = $omitted > 0;
        $content = implode("\n\n", array_column($included, 'content'));

        if ($truncated) {
            $content .= ($content === '' ? '' : "\n\n")
                .'[Docent notice: '.$omitted.' page'.($omitted === 1 ? ' was' : 's were').' omitted because the configured corpus budget was reached.]';
        }

        $citations = array_map(static fn (array $page): array => [
            'slug' => $page['slug'],
            'title' => $page['title'],
            'url' => $page['url'],
        ], $included);

        $version = sha1(implode('|', [
            $this->repository->directoryHash(),
            $fingerprint,
            $widget ? 'widget' : 'reader',
            (string) $budget,
            implode(',', array_column($citations, 'slug')),
        ]));

        return new AiCorpus($content, $citations, $version, (int) ceil(strlen($content) / 4), $truncated, $omitted);
    }

    /**
     * @param  list<NavigationItem|NavigationGroup>  $nodes
     * @return list<NavigationItem>
     */
    private function flatten(array $nodes): array
    {
        $items = [];

        foreach ($nodes as $node) {
            if ($node instanceof NavigationItem) {
                $items[] = $node;
            } else {
                array_push($items, ...$node->items, ...$this->flatten($node->groups));
            }
        }

        return $items;
    }

    private function pageBlock(string $title, string $url, string $markdown): string
    {
        return "<docent-page title=\"{$this->attribute($title)}\" url=\"{$this->attribute($url)}\">\n"
            .$markdown."\n</docent-page>";
    }

    private function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
