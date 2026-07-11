<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\BulletList;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\Heading;
use STS\Docent\Documents\Ast\ListItem;
use STS\Docent\Documents\Ast\OrderedList;
use STS\Docent\Documents\Ast\Table;
use STS\Docent\Documents\Ast\TableCell;
use STS\Docent\Documents\Ast\TableSection;
use STS\Docent\Documents\Document;

require_once __DIR__.'/Helpers.php';

it('generates github-style heading slugs deduplicated per document', function () {
    $doc = docParse(<<<'MD'
    # Getting Started

    ## Set Up

    ## Set Up

    ## Set Up
    MD);

    $headings = docFindAll($doc, Heading::class);

    expect($headings[0]->slug)->toBe('getting-started')
        ->and($headings[1]->slug)->toBe('set-up')
        ->and($headings[2]->slug)->toBe('set-up-1')
        ->and($headings[3]->slug)->toBe('set-up-2');
});

it('parses bullet and ordered lists', function () {
    $doc = docParse(<<<'MD'
    - one
    - two

    3. three
    4. four
    MD);

    $bullet = docFind($doc, BulletList::class);
    $ordered = docFind($doc, OrderedList::class);

    expect($bullet->children)->toHaveCount(2)
        ->and($ordered)->not->toBeNull()
        ->and($ordered->start)->toBe(3);
});

it('parses task list items with checked state', function () {
    $doc = docParse("- [ ] todo\n- [x] done");

    $items = docFindAll($doc, ListItem::class);

    expect($items[0]->checked)->toBeFalse()
        ->and($items[1]->checked)->toBeTrue();
});

it('parses GFM tables with header and alignment', function () {
    $doc = docParse(<<<'MD'
    | Name | Count |
    |:-----|------:|
    | A    | 1     |
    MD);

    $table = docFind($doc, Table::class);
    $sections = docFindAll($table, TableSection::class);
    $cells = docFindAll($table, TableCell::class);

    expect($table)->not->toBeNull()
        ->and($sections[0]->header)->toBeTrue()
        ->and($cells[0]->header)->toBeTrue()
        ->and($cells[0]->align)->toBe('left')
        ->and($cells[1]->align)->toBe('right');
});

it('parses fenced code blocks with language and info', function () {
    $doc = docParse("```php\necho 1;\n```");

    $code = docFind($doc, CodeBlock::class);

    expect($code->language)->toBe('php')
        ->and(trim($code->code))->toBe('echo 1;');
});

it('is safely serializable for caching', function () {
    $doc = docParse("# Title\n\nText with {{ value:x }} and `code`.");

    $restored = unserialize(serialize($doc));

    expect($restored)->toBeInstanceOf(Document::class)
        ->and(docFind($restored, Heading::class)->slug)->toBe('title');
});
