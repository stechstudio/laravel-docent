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
     * Whether the given viewer may see this page: front matter `authorize`
     * (gate/ability) and `audience` both gate the whole page.
     */
    public function authorize(DocumentationContext $context): bool
    {
        $authorize = $this->frontMatter()->authorize();

        if ($authorize !== null && ! $context->can($authorize)) {
            return false;
        }

        $audience = $this->frontMatter()->audience();

        if ($audience !== null && ! $this->manager->audienceAllows($audience, $context)) {
            return false;
        }

        return true;
    }

    public function render(DocumentationContext $context): string
    {
        return $this->manager->renderDocument($this->document, $context);
    }

    /**
     * @return list<TocEntry>
     */
    public function toc(): array
    {
        return TableOfContents::build($this->document);
    }
}
