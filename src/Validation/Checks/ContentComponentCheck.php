<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\CodeGroup;
use STS\Docent\Documents\Ast\Frame;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\Step;
use STS\Docent\Documents\Ast\Steps;
use STS\Docent\Documents\Ast\Tab;
use STS\Docent\Documents\Ast\Tabs;
use STS\Docent\Documents\Ast\Video;
use STS\Docent\Support\VideoSource;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/** Validates the structural contracts of Docent's content components. */
final class ContentComponentCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document !== null) {
                yield from $this->inspect($document, null, $page->slug);
            }
        }
    }

    /** @return iterable<Issue> */
    private function inspect(Node $node, ?Node $parent, string $slug): iterable
    {
        if ($node instanceof Step && ! $parent instanceof Steps) {
            yield Issue::error('orphan-step', $slug, 'A step must be directly inside a steps block.', $node->line);
        }

        if ($node instanceof Tab && ! $parent instanceof Tabs) {
            yield Issue::error('orphan-tab', $slug, 'A tab must be directly inside a tabs block.', $node->line);
        }

        if ($node instanceof Steps && ! $this->hasDirectChild($node, Step::class)) {
            yield Issue::warning('empty-steps', $slug, 'The steps block does not contain any steps.', $node->line);
        }

        if ($node instanceof Tabs && ! $this->hasDirectChild($node, Tab::class)) {
            yield Issue::warning('empty-tabs', $slug, 'The tabs block does not contain any tabs.', $node->line);
        }

        if ($node instanceof Frame && ! $this->containsImage($node)) {
            yield Issue::warning('frame-without-image', $slug, 'The frame does not contain an image.', $node->line);
        }

        if ($node instanceof Video && trim($node->url) === '') {
            yield Issue::warning('video-missing-source', $slug, 'The video directive does not have a URL.', $node->line);
        } elseif ($node instanceof Video && VideoSource::parse($node->url) === null) {
            yield Issue::warning('video-unrecognized-source', $slug, 'The video URL is not a recognized provider or playable file.', $node->line);
        }

        if ($node instanceof CodeGroup && ! $this->hasDirectChild($node, CodeBlock::class)) {
            yield Issue::warning('empty-code-group', $slug, 'The code group does not contain any code blocks.', $node->line);
        }

        if ($node instanceof CodeGroup) {
            foreach ($node->children as $child) {
                if (! $child instanceof CodeBlock) {
                    yield Issue::error('invalid-code-group', $slug, 'A code group may contain only fenced code blocks.', $child->line ?? $node->line);
                }
            }
        }

        foreach ($node->children as $child) {
            yield from $this->inspect($child, $node, $slug);
        }
    }

    /** @param class-string<Node> $class */
    private function hasDirectChild(Node $node, string $class): bool
    {
        foreach ($node->children as $child) {
            if ($child instanceof $class) {
                return true;
            }
        }

        return false;
    }

    private function containsImage(Node $node): bool
    {
        foreach ($node->children as $child) {
            if ($child instanceof Image || $this->containsImage($child)) {
                return true;
            }
        }

        return false;
    }
}
