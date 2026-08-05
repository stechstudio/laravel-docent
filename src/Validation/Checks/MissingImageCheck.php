<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Content\PageReference;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Document;
use STS\Docent\Support\DocsImagePath;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags local image sources that cannot be served. `/`-rooted paths resolve
 * against the public directory; relative paths resolve against the page's own
 * source directory and are streamed through the docs image route. External URLs
 * (`http:`, `//`, `data:`) are skipped.
 *
 * Relative paths resolve through the same {@see DocsImagePath} the route uses,
 * so a passing check means the route will serve the file — not merely that some
 * file exists somewhere on disk.
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

            yield from $this->scan($document, $page, $context, []);
        }
    }

    /**
     * Walk a page and everything it includes.
     *
     * A partial is rendered in the *including* page's directory, so its
     * relative image paths resolve from there — which means the same partial
     * included from two directories resolves differently. Validating it under
     * each includer is the only way the check can agree with what the renderer
     * emits; a partial that wants a stable image should use a `/`-rooted path.
     *
     * @param  list<string>  $stack  Include names already open, so a cycle terminates.
     * @return iterable<Issue>
     */
    private function scan(Document $document, PageReference $page, CheckContext $context, array $stack): iterable
    {
        foreach (AstWalker::walk($document) as $node) {
            if ($node instanceof Image) {
                yield from $this->issuesFor($node, $page, $context);

                continue;
            }

            if (! $node instanceof IncludeNode || in_array($node->name, $stack, true)) {
                continue;
            }

            $partial = $context->partial($node->name);

            if ($partial !== null) {
                yield from $this->scan($partial, $page, $context, [...$stack, $node->name]);
            }
        }
    }

    /**
     * @return iterable<Issue>
     */
    private function issuesFor(Image $node, PageReference $page, CheckContext $context): iterable
    {
        $url = $node->url;

        if ($url === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $url) === 1) {
            return;
        }

        if (str_starts_with($url, '/')) {
            $path = rtrim($context->publicPath(), '/').(preg_replace('/[#?].*$/', '', $url) ?? $url);

            if (! is_file($path)) {
                yield Issue::error('missing-image', $page->slug, 'Image "'.$url.'" was not found on disk.', $node->line);
            }

            return;
        }

        $relative = DocsImagePath::relative($url, $page->directory);

        if ($relative === null) {
            yield Issue::error(
                'missing-image',
                $page->slug,
                'Image "'.$url.'" resolves outside the documentation directory, so it cannot be served.',
                $node->line,
            );

            return;
        }

        if (! DocsImagePath::servable($relative)) {
            yield Issue::error(
                'missing-image',
                $page->slug,
                'Image "'.$url.'" is not a file type Docent serves ('.implode(', ', DocsImagePath::extensions()).').',
                $node->line,
            );

            return;
        }

        if (DocsImagePath::file($context->docsPath(), $relative) === null) {
            yield Issue::error('missing-image', $page->slug, 'Image "'.$url.'" was not found on disk.', $node->line);
        }
    }
}
