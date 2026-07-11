<?php

declare(strict_types=1);

namespace STS\Docent;

use Illuminate\Support\Str;
use STS\Docent\Content\DocumentSource;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Renderer\TableOfContents;
use STS\Docent\Documents\Renderer\TocEntry;
use STS\Docent\Runtime\DocumentationContext;

/**
 * A resolved documentation page: its slug, parsed AST, and front matter, plus
 * the affordances the HTTP layer needs. Controllers talk to Page (and
 * {@see DocentManager}), never to renderers or the registry directly.
 */
final class Page
{
    public function __construct(
        public readonly string $slug,
        private readonly DocumentSource $source,
        private readonly Document $document,
        private readonly DocentManager $manager,
    ) {}

    public function document(): Document
    {
        return $this->document;
    }

    public function frontMatter(): FrontMatter
    {
        return $this->document->frontMatter();
    }

    public function title(): string
    {
        return $this->frontMatter()->title() ?? ($this->slug === '' ? 'Home' : Str::headline(Str::afterLast($this->slug, '/')));
    }

    public function description(): ?string
    {
        return $this->frontMatter()->description();
    }

    /**
     * The page layout: `docs` (default) or `landing`. The controller uses this
     * to pick the view and suppress the sidebar/TOC chrome.
     */
    public function layout(): string
    {
        return $this->frontMatter()->layout();
    }

    public function isLanding(): bool
    {
        return $this->layout() === 'landing';
    }

    /**
     * Hero CTA buttons with their hrefs resolved through the same internal-link
     * path as in-body markdown links.
     *
     * @return list<array{label: string, href: string, style: string}>
     */
    public function heroCta(): array
    {
        return array_map(
            fn (array $cta): array => [...$cta, 'href' => $this->manager->resolveUrl($cta['href'], $this->baseDir())],
            $this->frontMatter()->heroCta(),
        );
    }

    /**
     * Whether the given viewer may see this page: front matter `authorize`
     * (gate/ability) and `audience` both gate the whole page.
     */
    public function authorize(DocumentationContext $context): bool
    {
        return $this->manager->authorizes($this->frontMatter()->authorize(), $this->frontMatter()->audience(), $context);
    }

    public function render(DocumentationContext $context): string
    {
        return $this->manager->renderDocument($this->document, $context, $this->baseDir());
    }

    /**
     * The directory relative links resolve against — decided by the repository,
     * which is the only layer that knows whether a slug is a section index.
     */
    public function baseDir(): string
    {
        return $this->source->baseDir;
    }

    /**
     * The viewer's table of contents: headings inside conditional blocks
     * appear only when this viewer would see them on the page.
     *
     * @return list<TocEntry>
     */
    public function toc(?DocumentationContext $context = null): array
    {
        if ($context === null) {
            return TableOfContents::build($this->document);
        }

        return (new TableOfContents($this->manager->registry(), $context))->buildFor($this->document);
    }
}
