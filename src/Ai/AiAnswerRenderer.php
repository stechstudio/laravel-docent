<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\MarkdownConverter;
use STS\Docent\Ai\Renderers\AiAnswerImageRenderer;
use STS\Docent\Ai\Renderers\AiAnswerLinkRenderer;

final class AiAnswerRenderer
{
    /**
     * @param  list<array{url: string}>  $citations
     */
    public function render(string $markdown, array $citations): string
    {
        $environment = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addRenderer(Link::class, new AiAnswerLinkRenderer(array_column($citations, 'url')), 100);
        $environment->addRenderer(Image::class, new AiAnswerImageRenderer, 100);

        return trim((string) (new MarkdownConverter($environment))->convert($markdown));
    }
}
