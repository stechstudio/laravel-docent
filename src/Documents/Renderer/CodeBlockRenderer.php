<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use STS\Docent\Documents\Ast\CodeBlock;

/**
 * Renders a code block to HTML. Swappable so syntax highlighting can be
 * introduced in the UI milestone without touching the renderer.
 */
interface CodeBlockRenderer
{
    public function render(CodeBlock $node): string;
}
