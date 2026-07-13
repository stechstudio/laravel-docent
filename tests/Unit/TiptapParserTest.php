<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\Accordion;
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
use STS\Docent\Documents\Ast\Frame;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\HtmlBlock;
use STS\Docent\Documents\Ast\Image;
use STS\Docent\Documents\Ast\IncludeNode;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\Link;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\OrderedList;
use STS\Docent\Documents\Ast\Paragraph;
use STS\Docent\Documents\Ast\Step;
use STS\Docent\Documents\Ast\Steps;
use STS\Docent\Documents\Ast\Strikethrough;
use STS\Docent\Documents\Ast\Strong;
use STS\Docent\Documents\Ast\Tab;
use STS\Docent\Documents\Ast\Table;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\TableSection;
use STS\Docent\Documents\Ast\Tabs;
use STS\Docent\Documents\Ast\Text;
use STS\Docent\Documents\Ast\ThematicBreak;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\Parser\TiptapDocumentParser;

require_once __DIR__.'/Helpers.php';

function tiptapParse(array $doc): Document
{
    return (new TiptapDocumentParser)->parse(json_encode(['type' => 'doc', 'content' => $doc]));
}

it('parses paragraphs and headings with computed slugs', function () {
    $doc = tiptapParse([
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Getting Started']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello.']]],
    ]);

    $heading = docFind($doc, Heading::class);

    expect($heading->level)->toBe(2)
        ->and($heading->slug)->toBe('getting-started')
        ->and(docFind($doc, Paragraph::class))->not->toBeNull();
});

it('produces an empty front matter (metadata lives out of band)', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]]);

    expect($doc->frontMatter()->all())->toBe([]);
});

it('flattens text marks into wrapper nodes, with code as a leaf', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => 'em', 'marks' => [['type' => 'italic']]],
        ['type' => 'text', 'text' => 'struck', 'marks' => [['type' => 'strike']]],
        ['type' => 'text', 'text' => 'coded', 'marks' => [['type' => 'code']]],
    ]]]);

    expect(docFind($doc, Strong::class))->not->toBeNull()
        ->and(docFind($doc, Emphasis::class))->not->toBeNull()
        ->and(docFind($doc, Strikethrough::class))->not->toBeNull()
        ->and(docFind($doc, InlineCode::class)->code)->toBe('coded');
});

it('merges nested marks (bold + italic) into wrapper nesting', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'bold'], ['type' => 'italic']]],
    ]]]);

    expect(docFind($doc, Strong::class))->not->toBeNull()
        ->and(docFind($doc, Emphasis::class))->not->toBeNull();
});

it('parses a link mark as a Link node preserving the href verbatim', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Setup', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'getting-started/setup']]]],
    ]]]);

    $link = docFind($doc, Link::class);

    expect($link->destination)->toBe('getting-started/setup')
        ->and(docFind($link, Text::class)->content)->toBe('Setup');
});

it('parses a link mark whose href is a token as an AppLink destination', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Billing', 'marks' => [['type' => 'link', 'attrs' => ['href' => '{{ link:billing.settings }}']]]],
    ]]]);

    $link = docFind($doc, Link::class);

    expect($link->destination)->toBeInstanceOf(AppLink::class)
        ->and($link->destination->kind)->toBe(AppLinkKind::Link)
        ->and($link->destination->key)->toBe('billing.settings');
});

it('parses lists including task items and an ordered start', function () {
    $doc = tiptapParse([
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'attrs' => ['checked' => true], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Done']]]]],
            ['type' => 'listItem', 'attrs' => ['checked' => false], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Todo']]]]],
        ]],
        ['type' => 'orderedList', 'attrs' => ['start' => 3], 'content' => [
            ['type' => 'listItem', 'attrs' => ['checked' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Third']]]]],
        ]],
    ]);

    $items = docFindAll($doc, ListItem::class);

    expect(docFind($doc, BulletList::class))->not->toBeNull()
        ->and($items[0]->checked)->toBeTrue()
        ->and($items[1]->checked)->toBeFalse()
        ->and($items[2]->checked)->toBeNull()
        ->and(docFind($doc, OrderedList::class)->start)->toBe(3);
});

it('parses blockquotes, code blocks, and horizontal rules', function () {
    $doc = tiptapParse([
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Quoted']]]]],
        ['type' => 'codeBlock', 'attrs' => ['language' => 'php', 'title' => 'app/Foo.php'], 'content' => [['type' => 'text', 'text' => "echo 1;\n"]]],
        ['type' => 'horizontalRule'],
    ]);

    $code = docFind($doc, CodeBlock::class);

    expect(docFind($doc, BlockQuote::class))->not->toBeNull()
        ->and($code->language)->toBe('php')
        ->and($code->code)->toBe("echo 1;\n")
        ->and($code->info)->toBe('php title="app/Foo.php"')
        ->and(docFind($doc, ThematicBreak::class))->not->toBeNull();
});

it('reconstructs the table tree with a head section from flat rows', function () {
    $doc = tiptapParse([['type' => 'table', 'content' => [
        ['type' => 'tableRow', 'content' => [
            ['type' => 'tableHeader', 'attrs' => ['align' => 'left'], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Type']]]]],
            ['type' => 'tableHeader', 'attrs' => ['align' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Balance']]]]],
        ]],
        ['type' => 'tableRow', 'content' => [
            ['type' => 'tableCell', 'attrs' => ['align' => 'left'], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Asset']]]]],
            ['type' => 'tableCell', 'attrs' => ['align' => null], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Debit']]]]],
        ]],
    ]]]);

    $sections = docFindAll($doc, TableSection::class);
    $cells = docFindAll($doc, TableCell::class);

    expect(docFind($doc, Table::class))->not->toBeNull()
        ->and($sections)->toHaveCount(2)
        ->and($sections[0]->header)->toBeTrue()
        ->and($cells[0]->header)->toBeTrue()
        ->and($cells[0]->align)->toBe('left')
        ->and($cells[2]->header)->toBeFalse();
});

it('parses every docs* block node', function () {
    $doc = tiptapParse([
        ['type' => 'docsGate', 'attrs' => ['mode' => 'cannot', 'ability' => 'billing.manage', 'arguments' => ['a', 'b']], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x']]]]],
        ['type' => 'docsCondition', 'attrs' => ['condition' => 'beta', 'negated' => true, 'arguments' => []], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'y']]]]],
        ['type' => 'docsAudience', 'attrs' => ['name' => 'internal'], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'z']]]]],
        ['type' => 'docsCallout', 'attrs' => ['type' => 'warning', 'title' => 'Heads up'], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'w']]]]],
        ['type' => 'docsInclude', 'attrs' => ['name' => 'permissions-note']],
        ['type' => 'docsComponent', 'attrs' => ['name' => 'plan-usage', 'attributes' => ['plan' => 'pro']]],
        ['type' => 'docsHtml', 'attrs' => ['html' => '<aside>Raw</aside>']],
        ['type' => 'docsSteps', 'content' => [['type' => 'docsStep', 'attrs' => ['title' => 'Install'], 'content' => [['type' => 'paragraph']]]]],
        ['type' => 'docsAccordion', 'attrs' => ['title' => 'Question'], 'content' => [['type' => 'paragraph']]],
        ['type' => 'docsTabs', 'content' => [['type' => 'docsTab', 'attrs' => ['label' => 'iOS'], 'content' => [['type' => 'paragraph']]]]],
        ['type' => 'docsFrame', 'attrs' => ['caption' => 'Screenshot'], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'image', 'attrs' => ['src' => '/shot.png', 'alt' => 'Shot']]]]]],
    ]);

    $gate = docFind($doc, AuthorizationBlock::class);
    $condition = docFind($doc, ConditionBlock::class);

    expect($gate->mode)->toBe(AuthorizationMode::Cannot)
        ->and($gate->ability)->toBe('billing.manage')
        ->and($gate->arguments)->toBe(['a', 'b'])
        ->and($condition->condition)->toBe('beta')
        ->and($condition->negated)->toBeTrue()
        ->and(docFind($doc, AudienceBlock::class)->audience)->toBe('internal')
        ->and(docFind($doc, Callout::class)->type)->toBe(CalloutType::Warning)
        ->and(docFind($doc, Callout::class)->title)->toBe('Heads up')
        ->and(docFind($doc, IncludeNode::class)->name)->toBe('permissions-note')
        ->and(docFind($doc, ComponentNode::class)->attributes)->toBe(['plan' => 'pro'])
        ->and(docFind($doc, HtmlBlock::class)->html)->toBe('<aside>Raw</aside>')
        ->and(docFind($doc, Steps::class))->not->toBeNull()
        ->and(docFind($doc, Step::class)->title)->toBe('Install')
        ->and(docFind($doc, Accordion::class)->title)->toBe('Question')
        ->and(docFind($doc, Tabs::class))->not->toBeNull()
        ->and(docFind($doc, Tab::class)->label)->toBe('iOS')
        ->and(docFind($doc, Frame::class)->caption)->toBe('Screenshot');
});

it('parses cards containing cards containing paragraphs (nesting)', function () {
    $doc = tiptapParse([['type' => 'docsGate', 'attrs' => ['mode' => 'can', 'ability' => 'billing.manage', 'arguments' => []], 'content' => [
        ['type' => 'docsCards', 'attrs' => ['columns' => 3], 'content' => [
            ['type' => 'docsCard', 'attrs' => ['title' => 'One', 'icon' => 'rocket', 'href' => 'getting-started'], 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Body']]],
            ]],
        ]],
    ]]]);

    $group = docFind($doc, CardGroup::class);
    $card = docFind($doc, Card::class);

    expect(docFind($doc, AuthorizationBlock::class))->not->toBeNull()
        ->and($group->columns)->toBe(3)
        ->and($card->title)->toBe('One')
        ->and($card->icon)->toBe('rocket')
        ->and($card->href)->toBe('getting-started')
        ->and(docFind($card, Paragraph::class))->not->toBeNull();
});

it('parses inline value and app-link atoms', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [
        ['type' => 'docsValue', 'attrs' => ['key' => 'account.plan', 'arguments' => ['x']]],
        ['type' => 'docsAppLink', 'attrs' => ['kind' => 'route', 'key' => 'dashboard', 'parameters' => []]],
    ]]]);

    expect(docFind($doc, DynamicValue::class)->key)->toBe('account.plan')
        ->and(docFind($doc, DynamicValue::class)->arguments)->toBe(['x'])
        ->and(docFind($doc, AppLink::class)->kind)->toBe(AppLinkKind::Route)
        ->and(docFind($doc, AppLink::class)->key)->toBe('dashboard');
});

it('parses an image atom', function () {
    $doc = tiptapParse([['type' => 'paragraph', 'content' => [
        ['type' => 'image', 'attrs' => ['src' => 'diagram.png', 'alt' => 'Diagram', 'title' => 'A diagram']],
    ]]]);

    $image = docFind($doc, Image::class);

    expect($image->url)->toBe('diagram.png')
        ->and($image->alt)->toBe('Diagram')
        ->and($image->title)->toBe('A diagram');
});

it('throws on an unknown block node type, naming it', function () {
    expect(fn () => tiptapParse([['type' => 'mysteryBox', 'content' => []]]))
        ->toThrow(InvalidArgumentException::class, 'mysteryBox');
});

it('throws on an unknown inline node type, naming it', function () {
    expect(fn () => tiptapParse([['type' => 'paragraph', 'content' => [['type' => 'weirdInline']]]]))
        ->toThrow(InvalidArgumentException::class, 'weirdInline');
});

it('throws on a document without a doc root', function () {
    expect(fn () => (new TiptapDocumentParser)->parse('{"type":"paragraph"}'))
        ->toThrow(InvalidArgumentException::class, 'doc');
});

it('throws on malformed JSON', function () {
    expect(fn () => (new TiptapDocumentParser)->parse('{ not json'))
        ->toThrow(JsonException::class);
});
