<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\HtmlBlock;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\TiptapDocumentParser;
use STS\Docent\Documents\Serializer\AstToTiptap;
use STS\Docent\Documents\Serializer\MarkdownExporter;

require_once __DIR__.'/Helpers.php';

/**
 * Every real markdown document in the repo, as the round-trip battery: the
 * clean workbench demo and the miniature fixture trees. Intentionally-broken
 * fixtures (bad YAML, cycles) are excluded — they never parse.
 *
 * @return array<string, array{string}>
 */
function batteryFiles(): array
{
    $root = dirname(__DIR__, 2);
    $dirs = [
        $root.'/workbench/resources/docs',
        $root.'/tests/fixtures/docs',
        $root.'/tests/fixtures/clean-docs',
    ];

    $cases = [];

    foreach ($dirs as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'md') {
                $cases[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
            }
        }
    }

    return $cases;
}

it('semantically round-trips md -> AST -> tiptap -> AST -> md -> AST', function (string $path) {
    $markdown = file_get_contents($path);

    $ast1 = docParse($markdown);
    $ast2 = (new TiptapDocumentParser)->parse(json_encode((new AstToTiptap)->convert($ast1)));
    $exported = (new MarkdownExporter)->export($ast2);
    $ast3 = docParse($exported);

    // AST1 ≡ AST2 ≡ AST3, semantically (line numbers/whitespace normalized).
    expect(docSemanticEquals($ast1, $ast2))->toBeTrue()
        ->and(docSemanticEquals($ast2, $ast3))->toBeTrue();

    // Export is a fixpoint over the tiptap-derived AST.
    expect((new MarkdownExporter)->export($ast3))->toBe($exported);
})->with(batteryFiles());

it('carries raw HTML through docsHtml opaquely', function () {
    $html = "<aside class=\"promo\">\n  <strong>Raw</strong> &amp; unescaped\n</aside>";
    $document = new Document(new FrontMatter);
    $document->appendChild(new HtmlBlock($html));

    $tiptap = (new AstToTiptap)->convert($document);
    $roundTripped = (new TiptapDocumentParser)->parse(json_encode($tiptap));

    expect($tiptap['content'][0]['type'])->toBe('docsHtml')
        ->and($tiptap['content'][0]['attrs']['html'])->toBe($html)
        ->and(docFind($roundTripped, HtmlBlock::class)->html)->toBe($html);
});
