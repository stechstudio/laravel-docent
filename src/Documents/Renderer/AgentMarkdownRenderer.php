<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Closure;
use STS\Docent\Documents\Ast\Accordion;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AppLinkKind;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\BlockQuote;
use STS\Docent\Documents\Ast\BulletList;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\CardGroup;
use STS\Docent\Documents\Ast\ComponentNode;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Emphasis;
use STS\Docent\Documents\Ast\Frame;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\OrderedList;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\Step;
use STS\Docent\Documents\Ast\Steps;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Ast\Tab;
use STS\Docent\Documents\Ast\Tabs;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\InternalLink;

/**
 * Produces readable, viewer-safe markdown for HTTP and llms.txt consumers.
 * Unlike MarkdownExporter, this is a rendering format: contextual containers
 * are evaluated, includes are expanded, and Docent's UI directives disappear.
 */
final class AgentMarkdownRenderer
{
    use ResolvesVisibility;

    private const MAX_INCLUDE_DEPTH = 10;

    /** @var list<string> */
    private array $includeStack = [];

    /**
     * @param  ?Closure(string): ?Document  $includeResolver
     * @param  ?Closure(string): string  $markdownUrlResolver
     */
    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly DocumentationContext $context,
        private readonly string $baseDir = '',
        private readonly string $routePrefix = 'docs',
        private readonly ?Closure $includeResolver = null,
        private readonly ?Closure $markdownUrlResolver = null,
    ) {}

    public function render(Document $document, string $title, ?string $description = null): string
    {
        $filtered = new Document(new FrontMatter);
        $children = $this->transformChildren($document);

        if (($children[0] ?? null) instanceof Heading
            && $children[0]->level === 1
            && trim(NodeText::extract($children[0])) === trim($title)) {
            array_shift($children);
        }

        $filtered->setChildren($children);
        $body = trim((new MarkdownExporter)->export($filtered));
        $header = '# '.$title;

        if ($description !== null && trim($description) !== '') {
            $header .= "\n\n> ".trim($description);
        }

        return $header.($body === '' ? "\n" : "\n\n".$body."\n");
    }

    /** @return list<Node> */
    private function transformChildren(Node $node): array
    {
        $children = [];

        foreach ($node->children as $child) {
            array_push($children, ...$this->transform($child));
        }

        return $children;
    }

    /** @return list<Node> */
    private function transform(Node $node): array
    {
        if ($node instanceof AuthorizationBlock) {
            return $this->authorizationVisible($node, $this->context) ? $this->transformChildren($node) : [];
        }

        if ($node instanceof ConditionBlock) {
            return $this->conditionVisible($node, $this->registry, $this->context) ? $this->transformChildren($node) : [];
        }

        if ($node instanceof AudienceBlock) {
            return $this->audienceVisible($node, $this->registry, $this->context) ? $this->transformChildren($node) : [];
        }

        if ($node instanceof IncludeNode) {
            return $this->include($node);
        }

        if ($node instanceof DynamicValue) {
            return [new Text('{'.$this->registry->valueLabel($node->key).'}', $node->line)];
        }

        if ($node instanceof AppLink) {
            $url = $this->applicationLink($node);

            return $url === null ? [] : [new Text($url, $node->line)];
        }

        if ($node instanceof ComponentNode) {
            return [];
        }

        if ($node instanceof Callout) {
            return [$this->callout($node)];
        }

        if ($node instanceof CardGroup) {
            return [$this->cards($node->children)];
        }

        if ($node instanceof Card) {
            return [$this->cards([$node])];
        }

        if ($node instanceof Steps) {
            return [$this->steps($node)];
        }

        if ($node instanceof Step) {
            return [$this->stepsFrom([$node])];
        }

        if ($node instanceof Accordion) {
            return [$this->label($node->title, $node->line), ...$this->transformChildren($node)];
        }

        if ($node instanceof Tabs) {
            return $this->tabs($node);
        }

        if ($node instanceof Tab) {
            return [$this->label($node->label, $node->line), ...$this->transformChildren($node)];
        }

        if ($node instanceof Frame) {
            return $this->frame($node);
        }

        $copy = clone $node;

        if ($copy instanceof Link) {
            $copy->destination = $copy->destination instanceof AppLink
                ? ($this->applicationLink($copy->destination) ?? '#')
                : $this->absoluteDestination($copy->destination);
        }

        if ($copy instanceof Image && str_starts_with($copy->url, '/')) {
            $copy->url = url($copy->url);
        }

        $copy->setChildren($this->transformChildren($node));

        return [$copy];
    }

    /** @return list<Node> */
    private function include(IncludeNode $node): array
    {
        if ($this->includeResolver === null
            || in_array($node->name, $this->includeStack, true)
            || count($this->includeStack) >= self::MAX_INCLUDE_DEPTH) {
            return [];
        }

        $document = ($this->includeResolver)($node->name);

        if ($document === null) {
            return [];
        }

        $this->includeStack[] = $node->name;
        $children = $this->transformChildren($document);
        array_pop($this->includeStack);

        return $children;
    }

    private function callout(Callout $node): BlockQuote
    {
        $quote = new BlockQuote($node->line);
        $heading = new Paragraph($node->line);
        $strong = new Strong($node->line);
        $label = ucfirst($node->type->value).($node->title !== null ? ': '.$node->title : '');

        $strong->appendChild(new Text($label, $node->line));
        $heading->appendChild($strong);
        $quote->setChildren([$heading, ...$this->transformChildren($node)]);

        return $quote;
    }

    /** @param list<Node> $nodes */
    private function cards(array $nodes): BulletList
    {
        $list = new BulletList;

        foreach ($nodes as $node) {
            if (! $node instanceof Card) {
                continue;
            }

            $item = new ListItem(line: $node->line);
            $title = new Paragraph($node->line);
            $label = $node->title ?? 'Read more';

            if ($node->href !== null && $node->href !== '') {
                $link = new Link($this->absoluteDestination($node->href), line: $node->line);
                $link->appendChild(new Text($label, $node->line));
                $title->appendChild($link);
            } else {
                $strong = new Strong($node->line);
                $strong->appendChild(new Text($label, $node->line));
                $title->appendChild($strong);
            }

            $item->setChildren([$title, ...$this->transformChildren($node)]);
            $list->appendChild($item);
        }

        return $list;
    }

    private function steps(Steps $node): OrderedList
    {
        return $this->stepsFrom($node->children);
    }

    /** @param list<Node> $nodes */
    private function stepsFrom(array $nodes): OrderedList
    {
        $list = new OrderedList;

        foreach ($nodes as $node) {
            if (! $node instanceof Step) {
                continue;
            }

            $item = new ListItem(line: $node->line);
            $item->setChildren([$this->label($node->title, $node->line), ...$this->transformChildren($node)]);
            $list->appendChild($item);
        }

        return $list;
    }

    /** @return list<Node> */
    private function tabs(Tabs $node): array
    {
        $children = [];

        foreach ($node->children as $tab) {
            if (! $tab instanceof Tab) {
                continue;
            }

            array_push($children, $this->label($tab->label, $tab->line), ...$this->transformChildren($tab));
        }

        return $children;
    }

    /** @return list<Node> */
    private function frame(Frame $node): array
    {
        $children = array_values(array_filter(
            $this->transformChildren($node),
            fn (Node $child): bool => $this->containsImage($child),
        ));

        if ($node->caption !== null && $node->caption !== '') {
            $caption = new Paragraph($node->line);
            $emphasis = new Emphasis($node->line);
            $emphasis->appendChild(new Text($node->caption, $node->line));
            $caption->appendChild($emphasis);
            $children[] = $caption;
        }

        return $children;
    }

    private function containsImage(Node $node): bool
    {
        if ($node instanceof Image) {
            return true;
        }

        foreach ($node->children as $child) {
            if ($this->containsImage($child)) {
                return true;
            }
        }

        return false;
    }

    private function label(string $text, ?int $line): Paragraph
    {
        $paragraph = new Paragraph($line);
        $strong = new Strong($line);
        $strong->appendChild(new Text($text, $line));
        $paragraph->appendChild($strong);

        return $paragraph;
    }

    private function applicationLink(AppLink $node): ?string
    {
        $url = match ($node->kind) {
            AppLinkKind::Link => $this->registry->resolveLink($node->key, $this->context, $node->parameters),
            AppLinkKind::Route => route($node->key, $node->parameters),
        };

        return $url === null ? null : $this->absoluteUrl($url);
    }

    private function absoluteDestination(string $destination): string
    {
        $target = InternalLink::resolve($destination, $this->baseDir, $this->routePrefix);

        if ($target !== null && $this->markdownUrlResolver !== null) {
            return ($this->markdownUrlResolver)($target['slug']).$target['suffix'];
        }

        return $this->absoluteUrl($destination);
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $url) === 1) {
            return $url;
        }

        return url($url);
    }
}
