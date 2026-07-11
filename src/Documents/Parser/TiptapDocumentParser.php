<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser;

use InvalidArgumentException;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AppLinkKind;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\AuthorizationMode;
use STS\Docent\Documents\Ast\BlockQuote;
use STS\Docent\Documents\Ast\BulletList;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\CalloutType;
use STS\Docent\Documents\Ast\Card;
use STS\Docent\Documents\Ast\CardGroup;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\ComponentNode;
use STS\Docent\Documents\Ast\ConditionBlock;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Emphasis;
use STS\Docent\Documents\Ast\HardBreak;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\HtmlBlock;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\Node as AstNode;
use STS\Docent\Documents\Ast\OrderedList;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\Strikethrough;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Ast\Table;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\TableRow;
use STS\Docent\Documents\Ast\TableSection;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Ast\ThematicBreak;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\Markdown\TokenSyntax;
use STS\Docent\Documents\Serializer\AstToTiptap;

/**
 * Parses ProseMirror/Tiptap JSON (the editor's wire format) into the Docent
 * AST — the mirror of {@see AstToTiptap}. The
 * document body is the whole story: Tiptap pages keep their metadata in the
 * `docent_pages` front-matter column, never inside the JSON, so the parsed
 * document always carries an empty {@see FrontMatter} and the repository layer
 * supplies the real front matter afterwards.
 *
 * The schema is the closed contract in DESIGN.md §"Tiptap schema contract".
 * Every node and mark type has an explicit mapping; an unrecognized type is a
 * corrupt/foreign document and fails loudly rather than being dropped.
 */
final class TiptapDocumentParser implements DocumentParser
{
    private Slugger $slugger;

    public function parse(string $content): Document
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (($data['type'] ?? null) !== 'doc') {
            throw new InvalidArgumentException('Tiptap document must have a root node of type "doc".');
        }

        $this->slugger = new Slugger;

        $document = new Document(new FrontMatter);

        foreach ($this->contentOf($data) as $child) {
            $document->appendChild($this->convertBlock($child));
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function convertBlock(array $node): AstNode
    {
        $type = $this->typeOf($node);

        return match ($type) {
            'paragraph' => $this->withInlines(new Paragraph, $node),
            'heading' => $this->convertHeading($node),
            'blockquote' => $this->withBlocks(new BlockQuote, $node),
            'bulletList' => $this->withBlocks(new BulletList, $node),
            'orderedList' => $this->withBlocks(new OrderedList($this->intAttr($node, 'start', 1)), $node),
            'listItem' => $this->convertListItem($node),
            'codeBlock' => $this->convertCodeBlock($node),
            'horizontalRule' => new ThematicBreak,
            'table' => $this->convertTable($node),
            'docsGate' => $this->withBlocks(new AuthorizationBlock(
                AuthorizationMode::from($this->stringAttr($node, 'mode', 'can')),
                $this->stringAttr($node, 'ability'),
                $this->listAttr($node, 'arguments'),
            ), $node),
            'docsCondition' => $this->withBlocks(new ConditionBlock(
                $this->stringAttr($node, 'condition'),
                $this->boolAttr($node, 'negated'),
                $this->listAttr($node, 'arguments'),
            ), $node),
            'docsAudience' => $this->withBlocks(new AudienceBlock($this->stringAttr($node, 'name')), $node),
            'docsCallout' => $this->withBlocks(new Callout(
                CalloutType::tryFromName($this->stringAttr($node, 'type', 'note')) ?? CalloutType::Note,
                $this->nullableStringAttr($node, 'title'),
            ), $node),
            'docsCards' => $this->withBlocks(new CardGroup($this->intAttr($node, 'columns', 2)), $node),
            'docsCard' => $this->withBlocks(new Card(
                $this->nullableStringAttr($node, 'title'),
                $this->nullableStringAttr($node, 'icon'),
                $this->nullableStringAttr($node, 'href'),
            ), $node),
            'docsInclude' => new IncludeNode($this->stringAttr($node, 'name')),
            'docsComponent' => new ComponentNode($this->stringAttr($node, 'name'), $this->attributesAttr($node)),
            'docsHtml' => new HtmlBlock($this->stringAttr($node, 'html')),
            default => throw new InvalidArgumentException('Unknown Tiptap node type: "'.$type.'".'),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function convertHeading(array $node): Heading
    {
        $children = $this->convertInlines($node);
        $heading = new Heading($this->intAttr($node, 'level', 1), $this->slugger->slug($this->plainText($children)));
        $heading->setChildren($children);

        return $heading;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function convertListItem(array $node): ListItem
    {
        $checked = $node['attrs']['checked'] ?? null;

        return $this->withBlocks(new ListItem(is_bool($checked) ? $checked : null), $node);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function convertCodeBlock(array $node): CodeBlock
    {
        $language = $this->nullableStringAttr($node, 'language');
        $title = $this->nullableStringAttr($node, 'title');

        return new CodeBlock($this->textContent($node), $language, $this->codeInfo($language, $title));
    }

    /**
     * Rebuild the fence info string from the split language + title so the
     * exported markdown fence matches what the markdown parser originally read.
     */
    private function codeInfo(?string $language, ?string $title): ?string
    {
        $parts = array_filter([
            $language,
            $title !== null ? 'title="'.$title.'"' : null,
        ], static fn (?string $part): bool => $part !== null && $part !== '');

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * Rebuild the Docent table tree ({@see Table} → {@see TableSection} →
     * {@see TableRow} → {@see TableCell}) from Tiptap's flat table (rows of
     * header/data cells). Leading all-header rows become the head section.
     *
     * @param  array<string, mixed>  $node
     */
    private function convertTable(array $node): Table
    {
        $table = new Table;
        $headRows = [];
        $bodyRows = [];

        foreach ($this->contentOf($node) as $row) {
            [$tableRow, $isHeader] = $this->convertTableRow($row);

            if ($isHeader && $bodyRows === []) {
                $headRows[] = $tableRow;
            } else {
                $bodyRows[] = $tableRow;
            }
        }

        if ($headRows !== []) {
            $head = new TableSection(true);
            $head->setChildren($headRows);
            $table->appendChild($head);
        }

        $body = new TableSection(false);
        $body->setChildren($bodyRows);
        $table->appendChild($body);

        return $table;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array{0: TableRow, 1: bool} the row and whether every cell is a header
     */
    private function convertTableRow(array $node): array
    {
        $row = new TableRow;
        $allHeader = true;

        foreach ($this->contentOf($node) as $cell) {
            $isHeader = $this->typeOf($cell) === 'tableHeader';
            $allHeader = $allHeader && $isHeader;

            $tableCell = new TableCell($isHeader, $this->nullableStringAttr($cell, 'align'));
            $tableCell->setChildren($this->cellInlines($cell));
            $row->appendChild($tableCell);
        }

        return [$row, $allHeader && $row->hasChildren()];
    }

    /**
     * Tiptap table cells wrap their content in block nodes (a paragraph);
     * Docent cells hold inlines directly, so unwrap the paragraph(s).
     *
     * @param  array<string, mixed>  $cell
     * @return list<AstNode>
     */
    private function cellInlines(array $cell): array
    {
        $inlines = [];

        foreach ($this->contentOf($cell) as $block) {
            foreach ($this->convertInlines($block) as $inline) {
                $inlines[] = $inline;
            }
        }

        return $inlines;
    }

    /**
     * @template T of AstNode
     *
     * @param  T  $target
     * @param  array<string, mixed>  $node
     * @return T
     */
    private function withBlocks(AstNode $target, array $node): AstNode
    {
        foreach ($this->contentOf($node) as $child) {
            $target->appendChild($this->convertBlock($child));
        }

        return $target;
    }

    /**
     * @template T of AstNode
     *
     * @param  T  $target
     * @param  array<string, mixed>  $node
     * @return T
     */
    private function withInlines(AstNode $target, array $node): AstNode
    {
        $target->setChildren($this->convertInlines($node));

        return $target;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<AstNode>
     */
    private function convertInlines(array $node): array
    {
        $inlines = [];

        foreach ($this->contentOf($node) as $child) {
            $inlines[] = $this->convertInline($child);
        }

        return $inlines;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function convertInline(array $node): AstNode
    {
        $type = $this->typeOf($node);

        $inline = match ($type) {
            'text' => $this->convertText($node),
            'hardBreak' => new HardBreak,
            'image' => new Image(
                $this->stringAttr($node, 'src'),
                $this->stringAttr($node, 'alt'),
                $this->nullableStringAttr($node, 'title'),
            ),
            'docsValue' => new DynamicValue($this->stringAttr($node, 'key'), $this->listAttr($node, 'arguments')),
            'docsAppLink' => new AppLink(
                AppLinkKind::from($this->stringAttr($node, 'kind', 'link')),
                $this->stringAttr($node, 'key'),
                $this->listAttr($node, 'parameters'),
            ),
            default => throw new InvalidArgumentException('Unknown Tiptap inline node type: "'.$type.'".'),
        };

        // Inline atoms may still carry marks (e.g. a bolded value chip).
        return $type === 'text' ? $inline : $this->applyMarks($inline, $this->marksOf($node));
    }

    /**
     * A text node with its mark stack. `code` is the innermost mark (it produces
     * an {@see InlineCode} leaf rather than a {@see Text}); the remaining marks
     * wrap outward.
     *
     * @param  array<string, mixed>  $node
     */
    private function convertText(array $node): AstNode
    {
        $text = is_string($node['text'] ?? null) ? $node['text'] : '';
        $marks = $this->marksOf($node);

        $base = in_array('code', $this->markNames($marks), true)
            ? new InlineCode($text)
            : new Text($text);

        return $this->applyMarks($base, $marks);
    }

    /**
     * Wrap a base inline node in its marks. Order is fixed (strike, italic,
     * bold, then link outermost) so serialization is deterministic; `code` is
     * already baked into the base node.
     *
     * @param  list<array<string, mixed>>  $marks
     */
    private function applyMarks(AstNode $base, array $marks): AstNode
    {
        $byType = [];
        foreach ($marks as $mark) {
            $byType[$this->typeOf($mark)] = $mark;
        }

        foreach (['strike', 'italic', 'bold'] as $type) {
            if (isset($byType[$type])) {
                $base = $this->wrap($type, $base);
            }
        }

        if (isset($byType['link'])) {
            $base = $this->wrapLink($byType['link'], $base);
        }

        return $base;
    }

    private function wrap(string $markType, AstNode $inner): AstNode
    {
        $wrapper = match ($markType) {
            'bold' => new Strong,
            'italic' => new Emphasis,
            'strike' => new Strikethrough,
            default => throw new InvalidArgumentException('Unknown Tiptap mark type: "'.$markType.'".'),
        };

        $wrapper->appendChild($inner);

        return $wrapper;
    }

    /**
     * A `link` mark whose href begins with `{{` is the canonical spelling of an
     * {@see AppLink} destination (see the AstToTiptap contract); anything else
     * is a literal URL/slug preserved verbatim.
     *
     * @param  array<string, mixed>  $mark
     */
    private function wrapLink(array $mark, AstNode $inner): Link
    {
        $href = $this->stringAttr($mark, 'href');
        $token = str_starts_with(ltrim($href), '{{') ? TokenSyntax::parse($href) : null;

        $link = new Link($token instanceof AppLink ? $token : $href);
        $link->appendChild($inner);

        return $link;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<string>
     */
    private function markNames(array $marks): array
    {
        return array_map(fn (array $mark): string => $this->typeOf($mark), $marks);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function marksOf(array $node): array
    {
        $marks = $node['marks'] ?? [];

        return is_array($marks) ? array_values(array_filter($marks, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private function contentOf(array $node): array
    {
        $content = $node['content'] ?? [];

        return is_array($content) ? array_values(array_filter($content, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function textContent(array $node): string
    {
        $text = '';

        foreach ($this->contentOf($node) as $child) {
            if (is_string($child['text'] ?? null)) {
                $text .= $child['text'];
            }
        }

        return $text;
    }

    /**
     * @param  list<AstNode>  $nodes
     */
    private function plainText(array $nodes): string
    {
        $text = '';

        foreach ($nodes as $node) {
            $text .= match (true) {
                $node instanceof Text => $node->content,
                $node instanceof InlineCode => $node->code,
                default => $this->plainText($node->children),
            };
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function typeOf(array $node): string
    {
        $type = $node['type'] ?? null;

        if (! is_string($type) || $type === '') {
            throw new InvalidArgumentException('Tiptap node is missing a "type".');
        }

        return $type;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function stringAttr(array $node, string $key, string $default = ''): string
    {
        $value = $node['attrs'][$key] ?? null;

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function nullableStringAttr(array $node, string $key): ?string
    {
        $value = $node['attrs'][$key] ?? null;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function intAttr(array $node, string $key, int $default): int
    {
        $value = $node['attrs'][$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function boolAttr(array $node, string $key): bool
    {
        return (bool) ($node['attrs'][$key] ?? false);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function listAttr(array $node, string $key): array
    {
        $value = $node['attrs'][$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, string>
     */
    private function attributesAttr(array $node): array
    {
        $value = $node['attrs']['attributes'] ?? [];

        if (! is_array($value)) {
            return [];
        }

        $attributes = [];
        foreach ($value as $key => $item) {
            if (is_scalar($item)) {
                $attributes[(string) $key] = (string) $item;
            }
        }

        return $attributes;
    }
}
