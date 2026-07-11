<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use STS\Docent\Documents\Ast\CodeBlock;

/**
 * Plain, escaped `<pre><code>` output. Syntax highlighting arrives in the UI
 * milestone via an alternate {@see CodeBlockRenderer} implementation.
 */
final class DefaultCodeBlockRenderer implements CodeBlockRenderer
{
    public function render(CodeBlock $node): string
    {
        $class = $node->language !== null && $node->language !== ''
            ? ' class="language-'.e($node->language).'"'
            : '';

        return '<pre><code'.$class.'>'.e($node->code).'</code></pre>';
    }
}
