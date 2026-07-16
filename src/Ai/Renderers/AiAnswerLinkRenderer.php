<?php

declare(strict_types=1);

namespace STS\Docent\Ai\Renderers;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final readonly class AiAnswerLinkRenderer implements NodeRendererInterface
{
    /** @param list<string> $allowedUrls */
    public function __construct(private array $allowedUrls) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable|string
    {
        if (! $node instanceof Link) {
            throw new \InvalidArgumentException('Incompatible node type: '.$node::class);
        }

        $label = $childRenderer->renderNodes($node->children());

        if (! in_array($node->getUrl(), $this->allowedUrls, true)) {
            return $label;
        }

        return new HtmlElement('a', [
            'href' => $node->getUrl(),
            'class' => 'docent-assistant-link',
            'data-docent-assistant-citation' => '',
        ], $label);
    }
}
