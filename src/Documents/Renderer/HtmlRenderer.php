<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AppLinkKind;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\BlockQuote;
use STS\Docent\Documents\Ast\BulletList;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\ComponentNode;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Emphasis;
use STS\Docent\Documents\Ast\HardBreak;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\HtmlBlock;
use STS\Docent\Documents\Ast\HtmlInline;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Ast\OrderedList;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\SoftBreak;
use STS\Docent\Documents\Ast\Strikethrough;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Ast\Table;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\TableRow;
use STS\Docent\Documents\Ast\TableSection;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Ast\ThematicBreak;
use STS\Docent\Documents\Document;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * Renders a Docent AST to context-aware HTML.
 */
final class HtmlRenderer
{
    use ResolvesVisibility;

    private const MAX_INCLUDE_DEPTH = 10;

    private CodeBlockRenderer $codeBlockRenderer;

    /** @var list<string> */
    private array $includeStack = [];

    /**
     * @param  array<string, mixed>  $options  allow_html (bool), debug (bool), route_resolver (Closure)
     * @param  ?Closure(string): ?Document  $includeResolver
     * @param  ?Closure(string): ?string  $urlResolver  Resolver for slug-style internal links.
     */
    public function __construct(
        private readonly IntegrationRegistry $registry,
        private readonly DocumentationContext $context,
        private readonly array $options = [],
        private readonly ?Closure $includeResolver = null,
        private readonly ?Closure $urlResolver = null,
        ?CodeBlockRenderer $codeBlockRenderer = null,
    ) {
        $this->codeBlockRenderer = $codeBlockRenderer ?? new DefaultCodeBlockRenderer;
    }

    public function render(Node $node): string
    {
        return $this->renderChildren($node);
    }

    private function renderChildren(Node $node): string
    {
        $html = '';
        foreach ($node->children as $child) {
            $html .= $this->renderNode($child);
        }

        return $html;
    }

    private function renderNode(Node $node): string
    {
        return match (true) {
            // Blocks
            $node instanceof Heading => $this->renderHeading($node),
            $node instanceof Paragraph => '<p>'.$this->renderChildren($node).'</p>',
            $node instanceof BlockQuote => '<blockquote>'.$this->renderChildren($node).'</blockquote>',
            $node instanceof BulletList => '<ul>'.$this->renderChildren($node).'</ul>',
            $node instanceof OrderedList => $this->renderOrderedList($node),
            $node instanceof ListItem => $this->renderListItem($node),
            $node instanceof Table => '<table>'.$this->renderChildren($node).'</table>',
            $node instanceof TableSection => ($node->header ? '<thead>' : '<tbody>').$this->renderChildren($node).($node->header ? '</thead>' : '</tbody>'),
            $node instanceof TableRow => '<tr>'.$this->renderChildren($node).'</tr>',
            $node instanceof TableCell => $this->renderTableCell($node),
            $node instanceof CodeBlock => $this->codeBlockRenderer->render($node),
            $node instanceof ThematicBreak => '<hr />',
            $node instanceof HtmlBlock => $this->allowHtml() ? $node->html : '',
            $node instanceof Callout => $this->renderCallout($node),
            $node instanceof AuthorizationBlock => $this->authorizationVisible($node, $this->context) ? $this->renderChildren($node) : '',
            $node instanceof ConditionBlock => $this->conditionVisible($node, $this->registry, $this->context) ? $this->renderChildren($node) : '',
            $node instanceof AudienceBlock => $this->audienceVisible($node, $this->registry, $this->context) ? $this->renderChildren($node) : '',
            $node instanceof IncludeNode => $this->renderInclude($node),
            $node instanceof ComponentNode => $this->renderComponent($node),

            // Inlines
            $node instanceof Text => e($node->content),
            $node instanceof Emphasis => '<em>'.$this->renderChildren($node).'</em>',
            $node instanceof Strong => '<strong>'.$this->renderChildren($node).'</strong>',
            $node instanceof Strikethrough => '<del>'.$this->renderChildren($node).'</del>',
            $node instanceof InlineCode => '<code>'.e($node->code).'</code>',
            $node instanceof Link => $this->renderLink($node),
            $node instanceof Image => $this->renderImage($node),
            $node instanceof HardBreak => "<br />\n",
            $node instanceof SoftBreak => "\n",
            $node instanceof HtmlInline => $this->allowHtml() ? $node->html : '',
            $node instanceof DynamicValue => $this->renderDynamicValue($node),
            $node instanceof AppLink => $this->renderAppLink($node),

            default => '',
        };
    }

    private function renderHeading(Heading $node): string
    {
        $id = $node->slug !== '' ? ' id="'.e($node->slug).'"' : '';

        return '<h'.$node->level.$id.'>'.$this->renderChildren($node).'</h'.$node->level.'>';
    }

    private function renderOrderedList(OrderedList $node): string
    {
        $start = $node->start !== 1 ? ' start="'.$node->start.'"' : '';

        return '<ol'.$start.'>'.$this->renderChildren($node).'</ol>';
    }

    private function renderListItem(ListItem $node): string
    {
        if ($node->checked === null) {
            return '<li>'.$this->renderChildren($node).'</li>';
        }

        $checked = $node->checked ? ' checked' : '';

        return '<li class="docent-task-item"><input type="checkbox" disabled'.$checked.' />'.$this->renderChildren($node).'</li>';
    }

    private function renderTableCell(TableCell $node): string
    {
        $tag = $node->header ? 'th' : 'td';
        $style = $node->align !== null ? ' style="text-align: '.e($node->align).'"' : '';

        return '<'.$tag.$style.'>'.$this->renderChildren($node).'</'.$tag.'>';
    }

    private function renderCallout(Callout $node): string
    {
        $type = $node->type->value;

        $html = '<div class="docent-callout docent-callout-'.$type.'" data-callout="'.$type.'">';

        if ($node->title !== null && $node->title !== '') {
            $html .= '<div class="docent-callout-title">'.e($node->title).'</div>';
        }

        $html .= '<div class="docent-callout-content">'.$this->renderChildren($node).'</div>';

        return $html.'</div>';
    }

    private function renderInclude(IncludeNode $node): string
    {
        if ($this->includeResolver === null) {
            return '';
        }

        if (in_array($node->name, $this->includeStack, true) || count($this->includeStack) >= self::MAX_INCLUDE_DEPTH) {
            return $this->missing('include', $node->name);
        }

        $document = ($this->includeResolver)($node->name);

        if ($document === null) {
            return $this->missing('include', $node->name);
        }

        $this->includeStack[] = $node->name;
        $html = $this->renderChildren($document);
        array_pop($this->includeStack);

        return $html;
    }

    private function renderComponent(ComponentNode $node): string
    {
        $component = $this->registry->resolveComponent($node->name);

        if ($component === null) {
            return $this->missing('component', $node->name);
        }

        $output = $component->render($this->context, $node->attributes);

        // Component output is trusted app-authored HTML.
        return $output instanceof Htmlable ? $output->toHtml() : $output;
    }

    private function renderLink(Link $node): string
    {
        $href = $node->destination instanceof AppLink
            ? $this->resolveAppLink($node->destination)
            : $this->resolveUrl($node->destination);

        if ($href === null) {
            // Unresolved app link: still render the label, unlinked.
            return $this->renderChildren($node);
        }

        $title = $node->title !== null && $node->title !== '' ? ' title="'.e($node->title).'"' : '';

        return '<a href="'.e($href).'"'.$title.'>'.$this->renderChildren($node).'</a>';
    }

    private function renderImage(Image $node): string
    {
        $title = $node->title !== null && $node->title !== '' ? ' title="'.e($node->title).'"' : '';

        return '<img src="'.e($node->url).'" alt="'.e($node->alt).'"'.$title.' />';
    }

    private function renderDynamicValue(DynamicValue $node): string
    {
        $value = $this->registry->resolveValue($node->key, $this->context, $node->arguments);

        if ($value === null) {
            return $this->missing('value', $node->key);
        }

        // Dynamic values are always escaped.
        return e($value);
    }

    private function renderAppLink(AppLink $node): string
    {
        $href = $this->resolveAppLink($node);

        if ($href === null) {
            return $this->missing($node->kind->value, $node->key);
        }

        return '<a href="'.e($href).'">'.e($href).'</a>';
    }

    private function resolveAppLink(AppLink $node): ?string
    {
        return match ($node->kind) {
            AppLinkKind::Link => $this->registry->resolveLink($node->key, $this->context, $node->parameters),
            AppLinkKind::Route => $this->resolveRoute($node->key, $node->parameters),
        };
    }

    /**
     * @param  list<string>  $parameters
     */
    private function resolveRoute(string $name, array $parameters): ?string
    {
        $resolver = $this->options['route_resolver'] ?? static fn (string $name, array $parameters): string => route($name, $parameters);

        return $resolver($name, $parameters);
    }

    private function resolveUrl(string $destination): string
    {
        if ($this->urlResolver === null || ! $this->isInternalSlug($destination)) {
            return $destination;
        }

        return ($this->urlResolver)($destination) ?? $destination;
    }

    private function isInternalSlug(string $destination): bool
    {
        if ($destination === '') {
            return false;
        }

        // Skip absolute URLs, protocol-relative, anchors, and mailto/tel.
        return preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i', $destination) !== 1;
    }

    private function allowHtml(): bool
    {
        return (bool) ($this->options['allow_html'] ?? true);
    }

    private function missing(string $kind, string $name): string
    {
        if (! ($this->options['debug'] ?? false)) {
            return '';
        }

        return '<!-- docent: missing '.$kind.':'.e($name).' -->';
    }
}
