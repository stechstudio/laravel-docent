<?php

declare(strict_types=1);

namespace STS\Docent\Documents;

use STS\Docent\Documents\Ast\Node;

/**
 * The canonical document model: the AST root node plus its front matter.
 */
final class Document extends Node
{
    public function __construct(
        public readonly FrontMatter $frontMatter,
        ?int $line = null,
        public readonly ?HtmlPolicy $htmlPolicy = null,
    ) {
        parent::__construct($line);
    }

    public function frontMatter(): FrontMatter
    {
        return $this->frontMatter;
    }

    /**
     * A copy of this document with its front matter replaced — the seam that lets
     * a Tiptap source's out-of-band metadata override the empty front matter the
     * JSON parser produces.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    public function withFrontMatter(array $frontMatter): self
    {
        $replacement = new self(new FrontMatter($frontMatter), $this->line, $this->htmlPolicy);
        $replacement->setChildren($this->children);

        return $replacement;
    }

    /** A copy of this document rendered under a different HTML policy. */
    public function withHtmlPolicy(HtmlPolicy $policy): self
    {
        $replacement = new self($this->frontMatter, $this->line, $policy);
        $replacement->setChildren($this->children);

        return $replacement;
    }
}
