<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Serializer;

use STS\Docent\Documents\Ast\Accordion;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\BlockQuote;
use STS\Docent\Documents\Ast\BulletList;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\CardGroup;
use STS\Docent\Documents\Ast\CodeBlock;
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
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Parser\TiptapDocumentParser;

/**
 * Serializes the Docent AST into ProseMirror/Tiptap JSON — the format the
 * visual editor loads. This is the inverse of {@see TiptapDocumentParser}
 * and follows the closed schema in DESIGN.md §"Tiptap schema contract".
 *
 * Inline formatting is flattened the ProseMirror way: {@see Emphasis}/{@see Strong}/
 * {@see Strikethrough}/{@see InlineCode} wrappers become mark arrays on the text
 * nodes they contained (nested wrappers merge into one mark set). A {@see SoftBreak}
 * has no ProseMirror equivalent and becomes a single space.
 *
 * App-link convention: a markdown link whose destination is an {@see AppLink}
 * (`[label]({{ link:x }})`) is emitted as its label text carrying a normal
 * `link` mark whose `href` is the canonical token string `{{ link:x }}`. The
 * Tiptap parser and the markdown exporter both treat an href beginning with
 * `{{` as an app-link destination, so the pairing round-trips. A *standalone*
 * app-link in prose (a bare `{{ link:x }}` token) instead becomes a
 * `docsAppLink` inline atom.
 *
 * Inline raw HTML ({@see HtmlInline}) is the one input the closed schema cannot
 * represent verbatim (there is no inline HTML node — only the block-level
 * `docsHtml` widget); it degrades to plain text. Block-level raw HTML rides
 * through `docsHtml` opaquely.
 */
final class AstToTiptap
{
    /**
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    public function convert(Document $document): array
    {
        return [
            'type' => 'doc',
            'content' => $this->blocks($document->children),
        ];
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<array<string, mixed>>
     */
    private function blocks(array $nodes): array
    {
        return array_map($this->block(...), $nodes);
    }

    /**
     * @return array<string, mixed>
     */
    private function block(Node $node): array
    {
        return match (true) {
            $node instanceof Paragraph => ['type' => 'paragraph', 'content' => $this->inlines($node->children)],
            $node instanceof Heading => ['type' => 'heading', 'attrs' => ['level' => $node->level], 'content' => $this->inlines($node->children)],
            $node instanceof BlockQuote => ['type' => 'blockquote', 'content' => $this->blocks($node->children)],
            $node instanceof BulletList => ['type' => 'bulletList', 'content' => $this->blocks($node->children)],
            $node instanceof OrderedList => ['type' => 'orderedList', 'attrs' => ['start' => $node->start], 'content' => $this->blocks($node->children)],
            $node instanceof ListItem => ['type' => 'listItem', 'attrs' => ['checked' => $node->checked], 'content' => $this->blocks($node->children)],
            $node instanceof CodeBlock => $this->codeBlock($node),
            $node instanceof ThematicBreak => ['type' => 'horizontalRule'],
            $node instanceof Table => ['type' => 'table', 'content' => $this->tableRows($node)],
            $node instanceof HtmlBlock => ['type' => 'docsHtml', 'attrs' => ['html' => $node->html]],
            $node instanceof Callout => ['type' => 'docsCallout', 'attrs' => ['type' => $node->type->value, 'title' => $node->title], 'content' => $this->blocks($node->children)],
            $node instanceof AuthorizationBlock => ['type' => 'docsGate', 'attrs' => ['mode' => $node->mode->value, 'ability' => $node->ability, 'arguments' => $node->arguments], 'content' => $this->blocks($node->children)],
            $node instanceof ConditionBlock => ['type' => 'docsCondition', 'attrs' => ['condition' => $node->condition, 'negated' => $node->negated, 'arguments' => $node->arguments], 'content' => $this->blocks($node->children)],
            $node instanceof AudienceBlock => ['type' => 'docsAudience', 'attrs' => ['name' => $node->audience], 'content' => $this->blocks($node->children)],
            $node instanceof CardGroup => ['type' => 'docsCards', 'attrs' => ['columns' => $node->columns], 'content' => $this->blocks($node->children)],
            $node instanceof Card => ['type' => 'docsCard', 'attrs' => ['title' => $node->title, 'icon' => $node->icon, 'href' => $node->href], 'content' => $this->blocks($node->children)],
            $node instanceof Steps => ['type' => 'docsSteps', 'content' => $this->blocks($node->children)],
            $node instanceof Step => ['type' => 'docsStep', 'attrs' => ['title' => $node->title], 'content' => $this->blocks($node->children)],
            $node instanceof Accordion => ['type' => 'docsAccordion', 'attrs' => ['title' => $node->title], 'content' => $this->blocks($node->children)],
            $node instanceof Tabs => ['type' => 'docsTabs', 'content' => $this->blocks($node->children)],
            $node instanceof Tab => ['type' => 'docsTab', 'attrs' => ['label' => $node->label], 'content' => $this->blocks($node->children)],
            $node instanceof Frame => ['type' => 'docsFrame', 'attrs' => ['caption' => $node->caption], 'content' => $this->blocks($node->children)],
            $node instanceof IncludeNode => ['type' => 'docsInclude', 'attrs' => ['name' => $node->name]],
            $node instanceof ComponentNode => ['type' => 'docsComponent', 'attrs' => ['name' => $node->name, 'attributes' => (object) $node->attributes]],
            default => ['type' => 'paragraph', 'content' => $this->inlines([$node])],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function codeBlock(CodeBlock $node): array
    {
        return [
            'type' => 'codeBlock',
            'attrs' => ['language' => $node->language, 'title' => $this->codeTitle($node)],
            'content' => $node->code === '' ? [] : [['type' => 'text', 'text' => $node->code]],
        ];
    }

    private function codeTitle(CodeBlock $node): ?string
    {
        if ($node->info !== null && preg_match('/title="([^"]*)"/', $node->info, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Flatten the Docent table tree into Tiptap's flat rows-of-cells shape.
     *
     * @return list<array<string, mixed>>
     */
    private function tableRows(Table $table): array
    {
        $rows = [];

        foreach ($table->children as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            foreach ($section->children as $row) {
                if ($row instanceof TableRow) {
                    $rows[] = $this->tableRow($row);
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRow(TableRow $row): array
    {
        $cells = [];

        foreach ($row->children as $cell) {
            if ($cell instanceof TableCell) {
                $cells[] = [
                    'type' => $cell->header ? 'tableHeader' : 'tableCell',
                    'attrs' => ['align' => $cell->align],
                    'content' => [['type' => 'paragraph', 'content' => $this->inlines($cell->children)]],
                ];
            }
        }

        return ['type' => 'tableRow', 'content' => $cells];
    }

    /**
     * Flatten an inline subtree into ProseMirror inline nodes, threading the
     * active mark set through formatting wrappers and links.
     *
     * @param  list<Node>  $nodes
     * @param  array<string, array<string, mixed>>  $marks  keyed by mark type
     * @return list<array<string, mixed>>
     */
    private function inlines(array $nodes, array $marks = []): array
    {
        $out = [];

        foreach ($nodes as $node) {
            match (true) {
                $node instanceof Text => $out[] = $this->text($node->content, $marks),
                $node instanceof SoftBreak => $out[] = $this->text(' ', $marks),
                $node instanceof InlineCode => $out[] = $this->text($node->code, [...$marks, 'code' => ['type' => 'code']]),
                $node instanceof HtmlInline => $out[] = $this->text($node->html, $marks),
                $node instanceof HardBreak => $out[] = $this->markable(['type' => 'hardBreak'], $marks),
                $node instanceof Emphasis => $out = [...$out, ...$this->inlines($node->children, [...$marks, 'italic' => ['type' => 'italic']])],
                $node instanceof Strong => $out = [...$out, ...$this->inlines($node->children, [...$marks, 'bold' => ['type' => 'bold']])],
                $node instanceof Strikethrough => $out = [...$out, ...$this->inlines($node->children, [...$marks, 'strike' => ['type' => 'strike']])],
                $node instanceof Link => $out = [...$out, ...$this->inlines($node->children, [...$marks, 'link' => $this->linkMark($node)])],
                $node instanceof Image => $out[] = $this->markable(['type' => 'image', 'attrs' => ['src' => $node->url, 'alt' => $node->alt, 'title' => $node->title]], $marks),
                $node instanceof DynamicValue => $out[] = $this->markable(['type' => 'docsValue', 'attrs' => ['key' => $node->key, 'arguments' => $node->arguments]], $marks),
                $node instanceof AppLink => $out[] = $this->markable(['type' => 'docsAppLink', 'attrs' => ['kind' => $node->kind->value, 'key' => $node->key, 'parameters' => $node->parameters]], $marks),
                default => null,
            };
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $marks
     * @return array<string, mixed>
     */
    private function text(string $text, array $marks): array
    {
        return $this->markable(['type' => 'text', 'text' => $text], $marks);
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array<string, mixed>>  $marks
     * @return array<string, mixed>
     */
    private function markable(array $node, array $marks): array
    {
        if ($marks !== []) {
            $node['marks'] = array_values($marks);
        }

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function linkMark(Link $node): array
    {
        $href = $node->destination instanceof AppLink
            ? TokenString::appLink($node->destination)
            : $node->destination;

        return ['type' => 'link', 'attrs' => ['href' => $href]];
    }
}
