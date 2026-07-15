<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Sanitizes browser-authored documentation HTML while retaining the ordinary
 * structural markup, classes, links, and media authors expect in help content.
 */
final class ContentHtmlSanitizer
{
    private readonly HtmlSanitizerInterface $sanitizer;

    public function __construct(?HtmlSanitizerInterface $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowAttribute('class', '*')
                ->allowAttribute('aria-label', '*')
                ->allowAttribute('aria-labelledby', '*')
                ->allowAttribute('aria-describedby', '*')
                ->allowAttribute('aria-hidden', '*')
                ->allowAttribute('aria-live', '*')
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->withMaxInputLength(-1),
        );
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
