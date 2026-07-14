<?php

declare(strict_types=1);

namespace STS\Docent\Ai\Renderers;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class AiAnswerImageRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string|null
    {
        Image::assertInstanceOf($node);

        return $childRenderer->renderNodes($node->children());
    }
}
