<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote as CmBlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode as CmFencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading as CmHeading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock as CmHtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode as CmIndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock as CmListBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\ListItem as CmListItem;
use League\CommonMark\Extension\CommonMark\Node\Block\ThematicBreak as CmThematicBreak;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code as CmCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Emphasis as CmEmphasis;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline as CmHtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image as CmImage;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link as CmLink;
use League\CommonMark\Extension\CommonMark\Node\Inline\Strong as CmStrong;
use League\CommonMark\Extension\Strikethrough\Strikethrough as CmStrikethrough;
use League\CommonMark\Extension\Table\Table as CmTable;
use League\CommonMark\Extension\Table\TableCell as CmTableCell;
use League\CommonMark\Extension\Table\TableRow as CmTableRow;
use League\CommonMark\Extension\Table\TableSection as CmTableSection;
use League\CommonMark\Extension\TaskList\TaskListItemMarker;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document as CmDocument;
use League\CommonMark\Node\Block\Paragraph as CmParagraph;
use League\CommonMark\Node\Inline\Newline as CmNewline;
use League\CommonMark\Node\Inline\Text as CmText;
use League\CommonMark\Node\Node as CmNode;
use STS\Docent\Documents\Ast\Accordion;
use STS\Docent\Documents\Ast\AppLink;
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
use STS\Docent\Documents\Ast\Node as AstNode;
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
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\Markdown\Node\ComponentBlock;
use STS\Docent\Documents\Parser\Markdown\Node\DirectiveBlock;
use STS\Docent\Documents\Parser\Markdown\Node\DocentTokenInline;
use STS\Docent\Documents\Parser\Markdown\Node\IncludeDirectiveBlock;
use STS\Docent\Documents\Parser\Slugger;

/**
 * Converts a parsed CommonMark tree into the Docent AST. CommonMark node types
 * never escape this class.
 */
final class AstConverter
{
    private Slugger $slugger;

    public function convert(CmDocument $document, FrontMatter $frontMatter): Document
    {
        $this->slugger = new Slugger;

        $root = new Document($frontMatter, $document->getStartLine());

        foreach ($document->children() as $child) {
            $node = $this->convertBlock($child);
            if ($node !== null) {
                $root->appendChild($node);
            }
        }

        return $root;
    }

    private function convertBlock(AbstractBlock $node): ?AstNode
    {
        $line = $node->getStartLine();

        return match (true) {
            $node instanceof CmHeading => $this->convertHeading($node, $line),
            $node instanceof CmParagraph => $this->convertParagraph($node, $line),
            $node instanceof CmBlockQuote => $this->withBlockChildren(new BlockQuote($line), $node),
            $node instanceof CmListBlock => $this->convertList($node, $line),
            $node instanceof CmListItem => $this->convertListItem($node, $line),
            $node instanceof CmFencedCode => new CodeBlock(
                TokenSyntax::restore($node->getLiteral()) ?? '',
                $this->firstWord($node->getInfo()),
                $node->getInfo(),
                $line,
            ),
            $node instanceof CmIndentedCode => new CodeBlock(TokenSyntax::restore($node->getLiteral()) ?? '', null, null, $line),
            $node instanceof CmThematicBreak => new ThematicBreak($line),
            $node instanceof CmHtmlBlock => new HtmlBlock(TokenSyntax::restore($node->getLiteral()) ?? '', $line),
            $node instanceof CmTable => $this->withBlockChildren(new Table($line), $node),
            $node instanceof CmTableSection => $this->withBlockChildren(new TableSection($node->isHead(), $line), $node),
            $node instanceof CmTableRow => $this->withBlockChildren(new TableRow($line), $node),
            $node instanceof CmTableCell => $this->convertTableCell($node, $line),
            $node instanceof DirectiveBlock => $this->convertDirective($node, $line),
            $node instanceof IncludeDirectiveBlock => new IncludeNode($node->name, $line),
            $node instanceof ComponentBlock => new ComponentNode($node->name, TokenSyntax::restoreDeep($node->attributes), $line),
            default => null,
        };
    }

    private function convertHeading(CmHeading $node, ?int $line): Heading
    {
        $children = $this->convertInlines($node, $line);
        $slug = $this->slugger->slug($this->plainText($children));

        $heading = new Heading($node->getLevel(), $slug, $line);
        $heading->setChildren($children);

        return $heading;
    }

    private function convertParagraph(CmParagraph $node, ?int $line): ?Paragraph
    {
        $children = $this->convertInlines($node, $line);

        // Drop orphaned closing fences that CommonMark folded into a paragraph.
        if (count($children) === 1 && $children[0] instanceof Text && preg_match('/^:{3,}$/', trim($children[0]->content)) === 1) {
            return null;
        }

        $paragraph = new Paragraph($line);
        $paragraph->setChildren($children);

        return $paragraph;
    }

    private function convertList(CmListBlock $node, ?int $line): AstNode
    {
        $data = $node->getListData();

        $list = $data->type === CmListBlock::TYPE_ORDERED
            ? new OrderedList($data->start ?? 1, $line)
            : new BulletList($line);

        return $this->withBlockChildren($list, $node);
    }

    private function convertListItem(CmListItem $node, ?int $line): ListItem
    {
        $item = new ListItem($this->taskState($node), $line);

        return $this->withBlockChildren($item, $node);
    }

    private function convertTableCell(CmTableCell $node, ?int $line): TableCell
    {
        $cell = new TableCell($node->getType() === CmTableCell::TYPE_HEADER, $node->getAlign(), $line);
        $cell->setChildren($this->convertInlines($node, $line));

        return $cell;
    }

    private function convertDirective(DirectiveBlock $node, ?int $line): AstNode
    {
        $attributes = TokenSyntax::restoreDeep($node->attributes);
        $shorthand = TokenSyntax::restore($node->shorthand);
        $arguments = $this->splitArguments($attributes['arguments'] ?? null);

        $block = match ($node->name) {
            'can' => new AuthorizationBlock(AuthorizationMode::Can, $attributes['ability'] ?? $shorthand ?? '', $arguments, $line),
            'cannot' => new AuthorizationBlock(AuthorizationMode::Cannot, $attributes['ability'] ?? $shorthand ?? '', $arguments, $line),
            'when' => new ConditionBlock($attributes['condition'] ?? $shorthand ?? '', false, $arguments, $line),
            'unless' => new ConditionBlock($attributes['condition'] ?? $shorthand ?? '', true, $arguments, $line),
            'audience' => new AudienceBlock($attributes['name'] ?? $shorthand ?? '', $line),
            'cards' => new CardGroup($this->cardColumns($attributes['columns'] ?? null), $line),
            'card' => new Card(
                $attributes['title'] ?? $shorthand,
                $attributes['icon'] ?? null,
                $attributes['href'] ?? null,
                $line,
            ),
            'steps' => new Steps($line),
            'step' => new Step($attributes['title'] ?? $shorthand ?? '', $line),
            'accordion' => new Accordion($attributes['title'] ?? $shorthand ?? '', $line),
            'tabs' => new Tabs($line),
            'tab' => new Tab($attributes['label'] ?? $shorthand ?? '', $line),
            'frame' => new Frame($attributes['caption'] ?? $shorthand, $line),
            default => new Callout(
                CalloutType::tryFromName($node->name) ?? CalloutType::Note,
                $attributes['title'] ?? $shorthand,
                $line,
            ),
        };

        return $this->withBlockChildren($block, $node);
    }

    /**
     * @template T of AstNode
     *
     * @param  T  $target
     * @return T
     */
    private function withBlockChildren(AstNode $target, AbstractBlock $node): AstNode
    {
        foreach ($node->children() as $child) {
            $converted = $this->convertBlock($child);
            if ($converted !== null) {
                $target->appendChild($converted);
            }
        }

        return $target;
    }

    /**
     * @return list<AstNode>
     */
    private function convertInlines(CmNode $node, ?int $line): array
    {
        $children = [];
        foreach ($node->children() as $child) {
            $converted = $this->convertInline($child, $line);
            if ($converted !== null) {
                $children[] = $converted;
            }
        }

        return $children;
    }

    private function convertInline(CmNode $node, ?int $line): ?AstNode
    {
        return match (true) {
            $node instanceof CmText => new Text(TokenSyntax::restore($node->getLiteral()) ?? '', $line),
            $node instanceof CmCode => new InlineCode(TokenSyntax::restore($node->getLiteral()) ?? '', $line),
            $node instanceof CmEmphasis => $this->withInlineChildren(new Emphasis($line), $node, $line),
            $node instanceof CmStrong => $this->withInlineChildren(new Strong($line), $node, $line),
            $node instanceof CmStrikethrough => $this->withInlineChildren(new Strikethrough($line), $node, $line),
            $node instanceof CmLink => $this->convertLink($node, $line),
            $node instanceof CmImage => new Image(TokenSyntax::restore($node->getUrl()) ?? '', $this->plainText($this->convertInlines($node, $line)), TokenSyntax::restore($node->getTitle()), $line),
            $node instanceof CmNewline => $node->getType() === CmNewline::HARDBREAK ? new HardBreak($line) : new SoftBreak($line),
            $node instanceof CmHtmlInline => new HtmlInline(TokenSyntax::restore($node->getLiteral()) ?? '', $line),
            $node instanceof DocentTokenInline => $this->convertToken($node, $line),
            $node instanceof TaskListItemMarker => null,
            default => null,
        };
    }

    private function convertLink(CmLink $node, ?int $line): Link
    {
        // CommonMark percent-encodes link destinations, so decode before probing
        // for a `{{ link:... }}` / `{{ route:... }}` token.
        $token = TokenSyntax::parse(rawurldecode($node->getUrl()), $line);
        $destination = $token instanceof AppLink ? $token : (TokenSyntax::restore($node->getUrl()) ?? '');

        $link = new Link($destination, TokenSyntax::restore($node->getTitle()), $line);
        $link->setChildren($this->convertInlines($node, $line));

        return $link;
    }

    private function convertToken(DocentTokenInline $node, ?int $line): AstNode
    {
        $token = $node->node;
        $token->line = $line;

        return $token;
    }

    /**
     * @template T of AstNode
     *
     * @param  T  $target
     * @return T
     */
    private function withInlineChildren(AstNode $target, CmNode $node, ?int $line): AstNode
    {
        $target->setChildren($this->convertInlines($node, $line));

        return $target;
    }

    private function taskState(CmListItem $item): ?bool
    {
        foreach ($item->iterator() as $descendant) {
            if ($descendant instanceof TaskListItemMarker) {
                return $descendant->isChecked();
            }
        }

        return null;
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

    private function firstWord(?string $info): ?string
    {
        if ($info === null) {
            return null;
        }

        $word = preg_split('/\s+/', trim($info))[0] ?? '';

        return $word !== '' ? $word : null;
    }

    /**
     * Grid width for a `::::cards` group: a positive integer, defaulting to 2.
     */
    private function cardColumns(?string $value): int
    {
        return $value !== null && ctype_digit($value) && (int) $value > 0 ? (int) $value : 2;
    }

    /**
     * @return list<string>
     */
    private function splitArguments(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
    }
}
