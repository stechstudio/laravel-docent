<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
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
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\CodeGroup;
use STS\Docent\Documents\Ast\ComponentNode;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Emphasis;
use STS\Docent\Documents\Ast\Frame;
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
use STS\Docent\Documents\Ast\SectionCards;
use STS\Docent\Documents\Ast\SoftBreak;
use STS\Docent\Documents\Ast\Step;
use STS\Docent\Documents\Ast\Steps;
use STS\Docent\Documents\Ast\Strikethrough;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Ast\Tab;
use STS\Docent\Documents\Ast\Table;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\TableRow;
use STS\Docent\Documents\Ast\TableSection;
use STS\Docent\Documents\Ast\Tabs;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Ast\ThematicBreak;
use STS\Docent\Documents\Ast\Video;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\HtmlPolicy;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;
use STS\Docent\Support\Icon;
use STS\Docent\Support\InternalLink;
use STS\Docent\Support\VideoSource;

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

    /** @var list<HtmlPolicy> */
    private array $htmlPolicyStack = [];

    private ContentHtmlSanitizer $htmlSanitizer;

    private int $componentIndex = 0;

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
        private readonly ?Closure $sectionCardsRenderer = null,
        ?CodeBlockRenderer $codeBlockRenderer = null,
        ?ContentHtmlSanitizer $htmlSanitizer = null,
    ) {
        $this->codeBlockRenderer = $codeBlockRenderer ?? new DefaultCodeBlockRenderer;
        $this->htmlSanitizer = $htmlSanitizer ?? new ContentHtmlSanitizer;
    }

    public function render(Node $node): string
    {
        if ($node instanceof Document && $node->htmlPolicy !== null) {
            $this->htmlPolicyStack[] = $node->htmlPolicy;

            try {
                return $this->renderChildren($node);
            } finally {
                array_pop($this->htmlPolicyStack);
            }
        }

        return $this->renderChildren($node);
    }

    private function renderChildren(Node $node): string
    {
        $sanitizeInlineHtml = false;

        if ($this->currentHtmlPolicy() === HtmlPolicy::Sanitized) {
            foreach ($node->children as $child) {
                if ($child instanceof HtmlInline) {
                    $sanitizeInlineHtml = true;
                    break;
                }
            }
        }

        $html = '';
        foreach ($node->children as $child) {
            $html .= $sanitizeInlineHtml && $child instanceof HtmlInline
                ? $child->html
                : $this->renderNode($child);
        }

        return $sanitizeInlineHtml ? $this->htmlSanitizer->sanitize($html) : $html;
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
            $node instanceof HtmlBlock => $this->renderHtml($node->html),
            $node instanceof Callout => $this->renderCallout($node),
            $node instanceof CardGroup => $this->renderCardGroup($node),
            $node instanceof Card => $this->renderCard($node),
            $node instanceof Steps => $this->renderSteps($node),
            $node instanceof Step => $this->renderStep($node),
            $node instanceof Accordion => $this->renderAccordion($node),
            $node instanceof Tabs => $this->renderTabs($node),
            $node instanceof Tab => $this->renderTab($node),
            $node instanceof Frame => $this->renderFrame($node),
            $node instanceof Video => $this->renderVideo($node),
            $node instanceof CodeGroup => $this->renderCodeGroup($node),
            $node instanceof SectionCards => $this->sectionCardsRenderer !== null ? ($this->sectionCardsRenderer)($node) : '',
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
            $node instanceof HtmlInline => $this->renderHtml($node->html),
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

    private function renderCardGroup(CardGroup $node): string
    {
        return '<div class="docent-cards" data-columns="'.$node->columns.'">'.$this->renderChildren($node).'</div>';
    }

    private function renderCard(Card $node): string
    {
        $href = $node->href !== null && $node->href !== '' ? $this->resolveUrl($node->href) : null;

        $inner = '';

        if ($node->icon !== null && ($icon = Icon::svg($node->icon)) !== null) {
            $inner .= '<div class="docent-card-icon">'.$icon.'</div>';
        }

        if ($node->title !== null && $node->title !== '') {
            $inner .= '<div class="docent-card-title">'.e($node->title).'</div>';
        }

        $body = $this->renderChildren($node);

        if ($body !== '') {
            $inner .= '<div class="docent-card-body">'.$body.'</div>';
        }

        if ($href !== null) {
            return '<a class="docent-card" href="'.e($href).'">'.$inner.'</a>';
        }

        return '<div class="docent-card">'.$inner.'</div>';
    }

    private function renderSteps(Steps $node): string
    {
        return '<ol class="docent-steps">'.$this->renderChildren($node).'</ol>';
    }

    private function renderStep(Step $node): string
    {
        return '<li class="docent-step"><div class="docent-step-marker" aria-hidden="true"></div>'
            .'<div class="docent-step-content"><div class="docent-step-title">'.e($node->title).'</div>'
            .$this->renderChildren($node).'</div></li>';
    }

    private function renderAccordion(Accordion $node): string
    {
        $id = 'docent-accordion-'.(++$this->componentIndex);

        return '<div class="docent-accordion" data-docent-accordion x-data="docentAccordion()" '
            .'x-on:docent:reveal-anchor.window="reveal($event.detail)">'
            .'<button type="button" class="docent-accordion-trigger" id="'.$id.'-trigger" '
            .'aria-controls="'.$id.'-panel" x-bind:aria-expanded="open" x-on:click="toggle()">'
            .'<span>'.e($node->title).'</span><span class="docent-accordion-chevron" aria-hidden="true">'
            .(Icon::svg('chevron-down') ?? '').'</span></button>'
            .'<div class="docent-accordion-panel" id="'.$id.'-panel" role="region" x-cloak '
            .'aria-labelledby="'.$id.'-trigger" x-show="open">'
            .'<div class="docent-accordion-content">'.$this->renderChildren($node).'</div></div></div>';
    }

    private function renderTabs(Tabs $node): string
    {
        $tabs = array_values(array_filter($node->children, static fn (Node $child): bool => $child instanceof Tab));
        $id = 'docent-tabs-'.(++$this->componentIndex);
        $labels = '';
        $panels = '';

        foreach ($tabs as $index => $tab) {
            $labels .= '<button type="button" role="tab" id="'.$id.'-tab-'.$index.'" '
                .'aria-controls="'.$id.'-panel-'.$index.'" x-bind:aria-selected="active === '.$index.'" '
                .'x-bind:tabindex="active === '.$index.' ? 0 : -1" x-on:click="activate('.$index.')" '
                .'x-on:keydown="onKeydown($event, '.$index.')">'.e($tab->label).'</button>';
            $panels .= '<div class="docent-tab-panel" role="tabpanel" id="'.$id.'-panel-'.$index.'" '
                .'aria-labelledby="'.$id.'-tab-'.$index.'" data-label="'.e($tab->label).'" x-show="active === '.$index.'" x-cloak>'
                .$this->renderChildren($tab).'</div>';
        }

        return '<div class="docent-tabs" data-docent-tabs x-data="docentTabs('.count($tabs).')" '
            .'x-on:docent:reveal-anchor.window="reveal($event.detail)">'
            .'<div class="docent-tab-list" role="tablist" aria-label="Content options">'.$labels.'</div>'
            .'<div class="docent-tab-panels">'.$panels.'</div></div>';
    }

    private function renderTab(Tab $node): string
    {
        return '<section class="docent-tab-panel"><div class="docent-tab-label">'.e($node->label).'</div>'
            .$this->renderChildren($node).'</section>';
    }

    private function renderFrame(Frame $node): string
    {
        $image = $this->firstImage($node);
        $lightbox = '';

        if ($image !== null) {
            $lightbox = '<div class="docent-lightbox" role="dialog" aria-modal="true" aria-label="Image preview" x-cloak '
                .'x-show="open" x-on:click.self="close()" x-on:keydown.escape.window="close()" x-ref="dialog" tabindex="-1">'
                .'<button type="button" class="docent-lightbox-close" aria-label="Close image preview" x-on:click="close()">'
                .(Icon::svg('x-mark') ?? '&times;').'</button>'
                .'<img src="'.e($image->url).'" alt="'.e($image->alt).'" /></div>';
        }

        $caption = $node->caption !== null && $node->caption !== ''
            ? '<figcaption>'.e($node->caption).'</figcaption>'
            : '';

        return '<figure class="docent-frame" data-docent-frame x-data="docentFrame()" '
            .'x-on:click="openFromImage($event)" x-on:keydown.tab.window="trap($event)">'
            .'<div class="docent-frame-content">'.$this->renderChildren($node).'</div>'.$caption.$lightbox.'</figure>';
    }

    private function firstImage(Node $node): ?Image
    {
        foreach ($node->children as $child) {
            if ($child instanceof Image) {
                return $child;
            }

            if (($image = $this->firstImage($child)) !== null) {
                return $image;
            }
        }

        return null;
    }

    private function renderVideo(Video $node): string
    {
        $source = VideoSource::parse($node->url);
        $caption = $node->caption !== null && $node->caption !== ''
            ? '<figcaption>'.e($node->caption).'</figcaption>'
            : '';

        if ($source === null) {
            $label = $node->caption !== null && $node->caption !== '' ? $node->caption : 'Video';

            return '<figure class="docent-video docent-video-unsupported"><a href="'.e($node->url).'">'
                .e($label).'</a>'.$caption.'</figure>';
        }

        if ($source->isFile()) {
            return '<figure class="docent-video"><div class="docent-video-shell">'
                .'<video controls preload="metadata"><source src="'.e($source->url).'" type="'.e($source->mimeType).'" /></video>'
                .'</div>'.$caption.'</figure>';
        }

        $label = $node->caption !== null && $node->caption !== '' ? $node->caption : 'Video';

        return '<figure class="docent-video" data-docent-video data-embed-url="'.e((string) $source->embedUrl).'" '
            .'x-data="docentVideo()"><div class="docent-video-shell">'
            .'<button type="button" class="docent-video-facade" x-show="! loaded" x-on:click="load()" '
            .'aria-label="'.e('Play '.$label).'"><span class="docent-video-play" aria-hidden="true"></span>'
            .'<span class="docent-video-facade-label">'.e($label).'</span></button>'
            .'<template x-if="loaded"><iframe x-bind:src="$root.dataset.embedUrl" title="'.e($label).'" '
            .'allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></template>'
            .'</div>'.$caption.'</figure>';
    }

    private function renderCodeGroup(CodeGroup $node): string
    {
        $blocks = array_values(array_filter($node->children, static fn (Node $child): bool => $child instanceof CodeBlock));
        $id = 'docent-code-group-'.(++$this->componentIndex);
        $labels = '';
        $panels = '';

        foreach ($blocks as $index => $block) {
            $label = $block->label();
            $labels .= '<button type="button" role="tab" id="'.$id.'-tab-'.$index.'" '
                .'aria-controls="'.$id.'-panel-'.$index.'" x-bind:aria-selected="active === '.$index.'" '
                .'x-bind:tabindex="active === '.$index.' ? 0 : -1" x-on:click="activate('.$index.')" '
                .'x-on:keydown="onKeydown($event, '.$index.')">'.e($label).'</button>';
            $panels .= '<div class="docent-tab-panel docent-code-group-panel" role="tabpanel" '
                .'id="'.$id.'-panel-'.$index.'" aria-labelledby="'.$id.'-tab-'.$index.'" '
                .'data-label="'.e($label).'" x-show="active === '.$index.'" x-cloak>'
                .$this->codeBlockRenderer->render($block).'</div>';
        }

        return '<div class="docent-tabs docent-code-group" data-docent-code-group x-data="docentTabs('.count($blocks).')">'
            .'<div class="docent-tab-list" role="tablist" aria-label="Code examples">'.$labels.'</div>'
            .'<div class="docent-tab-panels">'.$panels.'</div></div>';
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

        try {
            return $this->render($document);
        } finally {
            array_pop($this->includeStack);
        }
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
        $target = InternalLink::resolve(
            $destination,
            (string) ($this->options['base_dir'] ?? ''),
            (string) ($this->options['route_prefix'] ?? 'docs'),
        );

        if ($this->urlResolver === null || $target === null) {
            return $destination;
        }

        return (($this->urlResolver)($target['slug']) ?? $destination).$target['suffix'];
    }

    private function renderHtml(string $html): string
    {
        return match ($this->currentHtmlPolicy()) {
            HtmlPolicy::Trusted => $html,
            HtmlPolicy::Sanitized => $this->htmlSanitizer->sanitize($html),
            HtmlPolicy::Denied => '',
        };
    }

    private function currentHtmlPolicy(): HtmlPolicy
    {
        return $this->htmlPolicyStack !== []
            ? $this->htmlPolicyStack[array_key_last($this->htmlPolicyStack)]
            : (($this->options['allow_html'] ?? true) ? HtmlPolicy::Trusted : HtmlPolicy::Denied);
    }

    private function missing(string $kind, string $name): string
    {
        if (! ($this->options['debug'] ?? false)) {
            return '';
        }

        return '<!-- docent: missing '.$kind.':'.e($name).' -->';
    }
}
