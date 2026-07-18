<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\HtmlPolicy;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Renderer\HtmlRenderer;
use STS\Docent\Documents\Serializer\AstToTiptap;
use STS\Docent\Documents\Serializer\MarkdownExporter;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;
use STS\Docent\Runtime\IntegrationRegistry;

/**
 * @return list<class-string<Ast\Node>>
 */
function concreteAstNodeClasses(): array
{
    $classes = [];

    foreach (glob(__DIR__.'/../../src/Documents/Ast/*.php') ?: [] as $file) {
        $class = 'STS\\Docent\\Documents\\Ast\\'.pathinfo($file, PATHINFO_FILENAME);

        if (! class_exists($class) || ! is_subclass_of($class, Ast\Node::class)) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->isAbstract()) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * @param  list<Ast\Node>  $children
 */
function astContainer(Ast\Node $node, array $children): Ast\Node
{
    $node->setChildren($children);

    return $node;
}

function astTextParagraph(string $text = 'Fixture text.'): Ast\Paragraph
{
    /** @var Ast\Paragraph $paragraph */
    $paragraph = astContainer(new Ast\Paragraph, [new Ast\Text($text)]);

    return $paragraph;
}

function astTableCell(bool $header = false): Ast\TableCell
{
    /** @var Ast\TableCell $cell */
    $cell = astContainer(new Ast\TableCell($header, 'left'), [new Ast\Text($header ? 'Heading' : 'Value')]);

    return $cell;
}

function astTableRow(bool $header = false): Ast\TableRow
{
    /** @var Ast\TableRow $row */
    $row = astContainer(new Ast\TableRow, [astTableCell($header)]);

    return $row;
}

function astTableSection(bool $header = false): Ast\TableSection
{
    /** @var Ast\TableSection $section */
    $section = astContainer(new Ast\TableSection($header), [astTableRow($header)]);

    return $section;
}

function astTable(): Ast\Table
{
    /** @var Ast\Table $table */
    $table = astContainer(new Ast\Table, [astTableSection(true), astTableSection()]);

    return $table;
}

/**
 * @param  class-string  $class
 */
function astFixture(string $class): ?Ast\Node
{
    return match ($class) {
        Ast\Accordion::class => astContainer(new Ast\Accordion('Details'), [astTextParagraph()]),
        Ast\AppLink::class => new Ast\AppLink(Ast\AppLinkKind::Link, 'dashboard'),
        Ast\AudienceBlock::class => astContainer(new Ast\AudienceBlock('staff'), [astTextParagraph()]),
        Ast\AuthorizationBlock::class => astContainer(new Ast\AuthorizationBlock(Ast\AuthorizationMode::Can, 'docs.view'), [astTextParagraph()]),
        Ast\BlockQuote::class => astContainer(new Ast\BlockQuote, [astTextParagraph()]),
        Ast\BulletList::class => astContainer(new Ast\BulletList, [astContainer(new Ast\ListItem, [astTextParagraph()])]),
        Ast\Callout::class => astContainer(new Ast\Callout(Ast\CalloutType::Note, 'Remember'), [astTextParagraph()]),
        Ast\Card::class => astContainer(new Ast\Card('Guide', 'book-open', '/guide'), [astTextParagraph()]),
        Ast\CardGroup::class => astContainer(new Ast\CardGroup(2), [astContainer(new Ast\Card('Guide'), [astTextParagraph()])]),
        Ast\CodeBlock::class => new Ast\CodeBlock('<?php echo "fixture";', 'php', 'php title="Example"'),
        Ast\CodeGroup::class => astContainer(new Ast\CodeGroup, [new Ast\CodeBlock('echo "fixture";', 'php', 'php title="PHP"')]),
        Ast\ComponentNode::class => new Ast\ComponentNode('badge', ['tone' => 'info']),
        Ast\ConditionBlock::class => astContainer(new Ast\ConditionBlock('enabled'), [astTextParagraph()]),
        Ast\DynamicValue::class => new Ast\DynamicValue('plan'),
        Ast\Emphasis::class => astContainer(new Ast\Emphasis, [new Ast\Text('emphasis')]),
        Ast\Frame::class => astContainer(new Ast\Frame('Screenshot'), [astTextParagraph()]),
        Ast\HardBreak::class => new Ast\HardBreak,
        Ast\Heading::class => astContainer(new Ast\Heading(2, 'fixture-heading'), [new Ast\Text('Fixture heading')]),
        Ast\HtmlBlock::class => new Ast\HtmlBlock('<aside>Trusted HTML</aside>'),
        Ast\HtmlInline::class => new Ast\HtmlInline('<span>Trusted inline HTML</span>'),
        Ast\Image::class => new Ast\Image('/fixture.png', 'Fixture', 'Example image'),
        Ast\IncludeNode::class => new Ast\IncludeNode('fixture'),
        Ast\InlineCode::class => new Ast\InlineCode('fixture()'),
        Ast\Link::class => astContainer(new Ast\Link('/guide', 'Guide'), [new Ast\Text('Read the guide')]),
        Ast\ListItem::class => astContainer(new Ast\ListItem(false), [astTextParagraph()]),
        Ast\OrderedList::class => astContainer(new Ast\OrderedList(2), [astContainer(new Ast\ListItem, [astTextParagraph()])]),
        Ast\Paragraph::class => astTextParagraph(),
        Ast\SectionCards::class => new Ast\SectionCards('guides', 2),
        Ast\SoftBreak::class => new Ast\SoftBreak,
        Ast\Step::class => astContainer(new Ast\Step('Install'), [astTextParagraph()]),
        Ast\Steps::class => astContainer(new Ast\Steps, [astContainer(new Ast\Step('Install'), [astTextParagraph()])]),
        Ast\Strikethrough::class => astContainer(new Ast\Strikethrough, [new Ast\Text('obsolete')]),
        Ast\Strong::class => astContainer(new Ast\Strong, [new Ast\Text('important')]),
        Ast\Tab::class => astContainer(new Ast\Tab('PHP'), [astTextParagraph()]),
        Ast\Table::class => astTable(),
        Ast\TableCell::class => astTableCell(),
        Ast\TableRow::class => astTableRow(),
        Ast\TableSection::class => astTableSection(),
        Ast\Tabs::class => astContainer(new Ast\Tabs, [astContainer(new Ast\Tab('PHP'), [astTextParagraph()])]),
        Ast\Text::class => new Ast\Text('Fixture text.'),
        Ast\ThematicBreak::class => new Ast\ThematicBreak,
        Ast\Video::class => new Ast\Video('https://cdn.example.com/fixture.mp4', 'Fixture video'),
        default => null,
    };
}

function astDocument(Ast\Node $node): Document
{
    $document = new Document(new FrontMatter, htmlPolicy: HtmlPolicy::Trusted);
    $document->setChildren([$node]);

    return $document;
}

/**
 * Put inline and structural child nodes in the parent shape through which the
 * Markdown exporter intentionally handles them.
 */
function astRendererDocument(Ast\Node $node): Document
{
    $root = match (true) {
        $node instanceof Ast\AppLink,
        $node instanceof Ast\DynamicValue,
        $node instanceof Ast\Emphasis,
        $node instanceof Ast\HardBreak,
        $node instanceof Ast\HtmlInline,
        $node instanceof Ast\Image,
        $node instanceof Ast\InlineCode,
        $node instanceof Ast\Link,
        $node instanceof Ast\SoftBreak,
        $node instanceof Ast\Strikethrough,
        $node instanceof Ast\Strong,
        $node instanceof Ast\Text => astContainer(new Ast\Paragraph, [$node]),
        $node instanceof Ast\ListItem => astContainer(new Ast\BulletList, [$node]),
        $node instanceof Ast\TableSection => astContainer(new Ast\Table, [$node]),
        $node instanceof Ast\TableRow => astContainer(new Ast\Table, [astContainer(new Ast\TableSection, [$node])]),
        $node instanceof Ast\TableCell => astContainer(new Ast\Table, [
            astContainer(new Ast\TableSection, [astContainer(new Ast\TableRow, [$node])]),
        ]),
        default => $node,
    };

    return astDocument($root);
}

function astHtmlRenderer(): HtmlRenderer
{
    $registry = new IntegrationRegistry;
    $registry
        ->condition('enabled', static fn (): bool => true)
        ->audience('staff', static fn (): bool => true)
        ->value('plan', static fn (): string => 'Pro')
        ->link('dashboard', static fn (): string => '/dashboard')
        ->component('badge', new class implements DocumentationComponent
        {
            public function render(DocumentationContext $context, array $attributes): string
            {
                return '<span>Badge</span>';
            }
        });

    return new HtmlRenderer(
        registry: $registry,
        context: new DocumentationContext(gate: static fn (): bool => true),
        options: ['debug' => true],
        includeResolver: static fn (): Document => astDocument(astTextParagraph('Included text.')),
        sectionCardsRenderer: static fn (): string => '<div>Section cards</div>',
    );
}

/**
 * @return list<class-string<Ast\Node>>
 */
function lossyTiptapTextNodes(): array
{
    return [
        // Raw inline HTML intentionally becomes an ordinary Tiptap text node.
        Ast\HtmlInline::class,
        // A soft break intentionally normalizes to a single space.
        Ast\SoftBreak::class,
        // Emphasis intentionally flattens from an AST wrapper to an italic mark.
        Ast\Emphasis::class,
        // Strong intentionally flattens from an AST wrapper to a bold mark.
        Ast\Strong::class,
        // Strikethrough intentionally flattens from an AST wrapper to a strike mark.
        Ast\Strikethrough::class,
        // Links intentionally flatten from AST wrappers to link marks.
        Ast\Link::class,
        // Inline code intentionally flattens from an AST leaf to a code-marked text node.
        Ast\InlineCode::class,
    ];
}

/**
 * @param  array<string, mixed>  $tiptap
 */
function tiptapText(array $tiptap): string
{
    $text = is_string($tiptap['text'] ?? null) ? $tiptap['text'] : '';

    foreach ($tiptap['content'] ?? [] as $child) {
        if (is_array($child)) {
            $text .= tiptapText($child);
        }
    }

    return $text;
}

it('has a minimal fixture for every concrete AST node class', function () {
    foreach (concreteAstNodeClasses() as $class) {
        expect(astFixture($class), $class)->not->toBeNull();
    }
});

it('renders every AST fixture through HTML and Markdown without silently dropping it', function () {
    $htmlRenderer = astHtmlRenderer();
    $markdownExporter = new MarkdownExporter;

    foreach (concreteAstNodeClasses() as $class) {
        $fixture = astFixture($class);

        expect($fixture, $class)->not->toBeNull();

        $document = astRendererDocument($fixture);

        expect($htmlRenderer->render($document), 'HTML: '.$class)->not->toBe('')
            ->and($markdownExporter->export($document), 'Markdown: '.$class)->not->toBe('');
    }
});

it('reaches a stable Tiptap fixpoint for every AST fixture', function () {
    $serializer = new AstToTiptap;
    $parser = new TiptapDocumentParser;

    foreach (concreteAstNodeClasses() as $class) {
        $fixture = astFixture($class);

        expect($fixture, $class)->not->toBeNull();

        $first = $serializer->convert(astDocument($fixture));
        $json = json_encode($first, JSON_THROW_ON_ERROR);
        $second = $serializer->convert($parser->parse($json));

        if (in_array($class, lossyTiptapTextNodes(), true)) {
            expect(tiptapText($second), $class)->toBe(tiptapText($first));

            continue;
        }

        $normalizedFirst = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $normalizedSecond = json_decode(json_encode($second, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        expect($normalizedSecond, $class)->toBe($normalizedFirst);
    }
});
