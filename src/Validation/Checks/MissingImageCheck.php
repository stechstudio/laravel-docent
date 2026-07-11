<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\Image;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags local image sources that don't exist on disk. `/`-rooted paths resolve
 * against the public directory; relative paths against the page's own source
 * directory. External URLs (`http:`, `//`, `data:`) are skipped.
 */
final class MissingImageCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                if (! $node instanceof Image) {
                    continue;
                }

                $path = $this->localPath($node->url, $page->directory, $context);

                if ($path === null || is_file($path)) {
                    continue;
                }

                yield Issue::error('missing-image', $page->slug, 'Image "'.$node->url.'" was not found on disk.', $node->line);
            }
        }
    }

    private function localPath(string $url, string $directory, CheckContext $context): ?string
    {
        if ($url === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $url) === 1) {
            return null;
        }

        $url = preg_replace('/[#?].*$/', '', $url) ?? $url;

        if (str_starts_with($url, '/')) {
            return rtrim($context->publicPath(), '/').$url;
        }

        $base = $directory === '' ? $context->docsPath() : $context->docsPath().'/'.$directory;

        return $base.'/'.$url;
    }
}
