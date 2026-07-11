<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Serializer;

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AudienceBlock;
use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\AuthorizationMode;
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
use Symfony\Component\Yaml\Yaml;

/**
 * Serializes the Docent AST into normalized markdown — the canonical spelling
 * of every node (DESIGN.md §"Tiptap schema contract" → "Markdown export
 * spellings"). This is the total AST → markdown function that powers "View
 * markdown" and file export.
 *
 * Normalization: ATX headings, `-` bullets, `1.` ordered, `**`/`*`/`~~`
 * emphasis, backtick fences, GFM tables, and one blank line between blocks.
 * Container directives use the shortest fence that still reparses: a directive
 * is fenced one colon longer than the deepest directive nested inside it, so
 * `:::::gate` wraps `::::cards` wraps `:::card` and every closing fence matches
 * its opener by length. Export is deterministic and a fixpoint —
 * export(parse(export(x))) === export(x).
 *
 * Front matter is omitted by default (callers compose it); {@see withFrontMatter()}
 * prepends a YAML block for the file-export use case.
 */
final class MarkdownExporter
{
    /** @var array<string, mixed>|null */
    private ?array $frontMatter = null;

    /**
     * A copy of the exporter that prepends the given front matter as a YAML
     * block. An empty array is treated as no front matter.
     *
     * @param  array<string, mixed>  $frontMatter
     */
    public function withFrontMatter(array $frontMatter): self
    {
        $clone = clone $this;
        $clone->frontMatter = $frontMatter === [] ? null : $frontMatter;

        return $clone;
    }

    public function export(Document $document): string
    {
        $body = $this->blocks($document->children);

        $prefix = $this->frontMatter === null
            ? ''
            : '---'."\n".Yaml::dump($this->frontMatter).'---'."\n\n";

        return $prefix.($body === '' ? '' : $body."\n");
    }

    /**
     * @param  list<Node>  $nodes
     */
    private function blocks(array $nodes): string
    {
        $parts = [];

        foreach ($nodes as $node) {
            $rendered = $this->block($node);
            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        return implode("\n\n", $parts);
    }

    private function block(Node $node): string
    {
        return match (true) {
            $node instanceof Heading => str_repeat('#', $node->level).' '.$this->inline($node->children),
            $node instanceof Paragraph => $this->inline($node->children),
            $node instanceof BlockQuote => $this->blockQuote($node),
            $node instanceof BulletList => $this->bulletList($node),
            $node instanceof OrderedList => $this->orderedList($node),
            $node instanceof CodeBlock => $this->codeBlock($node),
            $node instanceof ThematicBreak => '---',
            $node instanceof Table => $this->table($node),
            $node instanceof HtmlBlock => rtrim($node->html, "\n"),
            $node instanceof IncludeNode => ':::include name="'.$node->name.'"',
            $node instanceof ComponentNode => $this->component($node),
            $node instanceof Callout, $node instanceof AuthorizationBlock,
            $node instanceof ConditionBlock, $node instanceof AudienceBlock,
            $node instanceof CardGroup, $node instanceof Card => $this->directive($node),
            default => '',
        };
    }

    private function blockQuote(BlockQuote $node): string
    {
        $inner = $this->blocks($node->children);

        return implode("\n", array_map(
            static fn (string $line): string => $line === '' ? '>' : '> '.$line,
            explode("\n", $inner),
        ));
    }

    private function bulletList(BulletList $node): string
    {
        $items = [];

        foreach ($node->children as $item) {
            if ($item instanceof ListItem) {
                $items[] = $this->listItem($item, $this->bulletMarker($item));
            }
        }

        return implode("\n", $items);
    }

    private function orderedList(OrderedList $node): string
    {
        $items = [];
        $number = $node->start;

        foreach ($node->children as $item) {
            if ($item instanceof ListItem) {
                $items[] = $this->listItem($item, $number.'. ');
                $number++;
            }
        }

        return implode("\n", $items);
    }

    /**
     * The bullet marker for a list item. Task items omit the trailing space:
     * CommonMark leaves the space that follows `[x]` inside the item's text, so
     * a marker of `- [x]` (not `- [x] `) reproduces the source exactly and keeps
     * export a fixpoint.
     */
    private function bulletMarker(ListItem $item): string
    {
        return match ($item->checked) {
            true => '- [x]',
            false => '- [ ]',
            null => '- ',
        };
    }

    /**
     * Render a list item: the marker prefixes the first line, and continuation
     * lines (wrapped blocks, nested lists) are indented to the marker width.
     */
    private function listItem(ListItem $item, string $marker): string
    {
        $content = $this->blocks($item->children);
        $indent = str_repeat(' ', strlen($marker));

        $lines = explode("\n", $content);
        $out = $marker.array_shift($lines);

        foreach ($lines as $line) {
            $out .= "\n".($line === '' ? '' : $indent.$line);
        }

        return $out;
    }

    private function codeBlock(CodeBlock $node): string
    {
        $info = $node->info ?? $node->language ?? '';
        $fence = $this->codeFence($node->code);

        return $fence.$info."\n".rtrim($node->code, "\n")."\n".$fence;
    }

    /**
     * A backtick fence at least three long, and longer than any backtick run in
     * the code so the fence can never be closed early.
     */
    private function codeFence(string $code): string
    {
        $longest = 0;
        if (preg_match_all('/`+/', $code, $matches) > 0) {
            $longest = max(array_map('strlen', $matches[0]));
        }

        return str_repeat('`', max(3, $longest + 1));
    }

    private function table(Table $node): string
    {
        $headerCells = null;
        $bodyRows = [];

        foreach ($node->children as $section) {
            if (! $section instanceof TableSection) {
                continue;
            }

            foreach ($section->children as $row) {
                if (! $row instanceof TableRow) {
                    continue;
                }

                if ($headerCells === null && $section->header) {
                    $headerCells = $this->cells($row);
                } else {
                    $bodyRows[] = $this->cells($row);
                }
            }
        }

        // A table authored without a head section: promote its first row.
        if ($headerCells === null) {
            $headerCells = array_shift($bodyRows) ?? [];
        }

        $lines = [
            $this->rowLine(array_map(fn (TableCell $cell): string => $this->inline($cell->children), $headerCells)),
            $this->separatorLine($headerCells),
        ];

        foreach ($bodyRows as $cells) {
            $lines[] = $this->rowLine(array_map(fn (TableCell $cell): string => $this->inline($cell->children), $cells));
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<TableCell>
     */
    private function cells(TableRow $row): array
    {
        return array_values(array_filter($row->children, static fn (Node $cell): bool => $cell instanceof TableCell));
    }

    /**
     * @param  list<string>  $cells
     */
    private function rowLine(array $cells): string
    {
        return '| '.implode(' | ', $cells).' |';
    }

    /**
     * @param  list<TableCell>  $cells
     */
    private function separatorLine(array $cells): string
    {
        $segments = array_map(static fn (TableCell $cell): string => match ($cell->align) {
            'left' => ':---',
            'right' => '---:',
            'center' => ':---:',
            default => '---',
        }, $cells);

        return '| '.implode(' | ', $segments).' |';
    }

    private function component(ComponentNode $node): string
    {
        $attributes = ' name="'.$node->name.'"';

        foreach ($node->attributes as $key => $value) {
            $attributes .= ' '.$key.'="'.$value.'"';
        }

        return '<docs-component'.$attributes.' />';
    }

    private function directive(Node $node): string
    {
        $fence = str_repeat(':', $this->fenceLength($node));
        $body = $this->blocks($node->children);

        $lines = [$fence.$this->directiveOpen($node)];
        if ($body !== '') {
            $lines[] = $body;
        }
        $lines[] = $fence;

        return implode("\n", $lines);
    }

    private function directiveOpen(Node $node): string
    {
        return match (true) {
            $node instanceof AuthorizationBlock => ($node->mode === AuthorizationMode::Can ? 'can' : 'cannot')
                .' ability="'.$node->ability.'"'.$this->arguments($node->arguments),
            $node instanceof ConditionBlock => ($node->negated ? 'unless' : 'when')
                .' condition="'.$node->condition.'"'.$this->arguments($node->arguments),
            $node instanceof AudienceBlock => 'audience name="'.$node->audience.'"',
            $node instanceof Callout => $node->type->value.($node->title !== null ? ' title="'.$node->title.'"' : ''),
            $node instanceof CardGroup => 'cards'.($node->columns !== 2 ? ' columns="'.$node->columns.'"' : ''),
            $node instanceof Card => 'card'
                .($node->title !== null ? ' title="'.$node->title.'"' : '')
                .($node->icon !== null ? ' icon="'.$node->icon.'"' : '')
                .($node->href !== null ? ' href="'.$node->href.'"' : ''),
            default => '',
        };
    }

    /**
     * @param  list<string>  $arguments
     */
    private function arguments(array $arguments): string
    {
        return $arguments === [] ? '' : ' arguments="'.implode(',', $arguments).'"';
    }

    /**
     * The colon count for a directive's fence: one longer than the deepest
     * directive nested within it (minimum three). Directives closed by a `:::`
     * fence are the only ones that count; single-line `:::include` and
     * `<docs-component>` never need a longer wrapper.
     */
    private function fenceLength(Node $node): int
    {
        $inner = 2;

        foreach ($this->nestedDirectives($node) as $directive) {
            $inner = max($inner, $this->fenceLength($directive));
        }

        return $inner + 1;
    }

    /**
     * The outermost directive-fence descendants of a node (recursion stops at
     * each one, since its own fence length already accounts for its subtree).
     *
     * @return list<Node>
     */
    private function nestedDirectives(Node $node): array
    {
        $found = [];

        foreach ($node->children as $child) {
            if ($this->isDirectiveFence($child)) {
                $found[] = $child;
            } else {
                $found = [...$found, ...$this->nestedDirectives($child)];
            }
        }

        return $found;
    }

    private function isDirectiveFence(Node $node): bool
    {
        return $node instanceof AuthorizationBlock
            || $node instanceof ConditionBlock
            || $node instanceof AudienceBlock
            || $node instanceof Callout
            || $node instanceof CardGroup
            || $node instanceof Card;
    }

    /**
     * @param  list<Node>  $nodes
     */
    private function inline(array $nodes): string
    {
        $out = '';

        foreach ($nodes as $node) {
            $out .= match (true) {
                $node instanceof Text => $node->content,
                $node instanceof SoftBreak => ' ',
                $node instanceof HardBreak => "\\\n",
                $node instanceof InlineCode => $this->inlineCode($node->code),
                $node instanceof Emphasis => '*'.$this->inline($node->children).'*',
                $node instanceof Strong => '**'.$this->inline($node->children).'**',
                $node instanceof Strikethrough => '~~'.$this->inline($node->children).'~~',
                $node instanceof Link => $this->link($node),
                $node instanceof Image => '!['.$node->alt.']('.$node->url.$this->title($node->title).')',
                $node instanceof DynamicValue => TokenString::value($node),
                $node instanceof AppLink => TokenString::appLink($node),
                $node instanceof HtmlInline => $node->html,
                default => '',
            };
        }

        return $out;
    }

    private function inlineCode(string $code): string
    {
        $longest = 0;
        if (preg_match_all('/`+/', $code, $matches) > 0) {
            $longest = max(array_map('strlen', $matches[0]));
        }

        if ($longest === 0) {
            return '`'.$code.'`';
        }

        $ticks = str_repeat('`', $longest + 1);

        return $ticks.' '.$code.' '.$ticks;
    }

    private function link(Link $node): string
    {
        $label = $this->inline($node->children);

        $destination = $node->destination instanceof AppLink
            ? TokenString::appLink($node->destination)
            : $node->destination;

        return '['.$label.']('.$destination.$this->title($node->title).')';
    }

    private function title(?string $title): string
    {
        return $title !== null && $title !== '' ? ' "'.$title.'"' : '';
    }
}
