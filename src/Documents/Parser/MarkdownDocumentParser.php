<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Parser\MarkdownParser;
use STS\Docent\Documents\Document;
use STS\Docent\Documents\FrontMatter;
use STS\Docent\Documents\Parser\Markdown\AstConverter;
use STS\Docent\Documents\Parser\Markdown\ComponentBlockStartParser;
use STS\Docent\Documents\Parser\Markdown\DirectiveBlockStartParser;
use STS\Docent\Documents\Parser\Markdown\TokenInlineParser;
use STS\Docent\Documents\Parser\Markdown\TokenSyntax;

/**
 * Parses Docent markdown into the Docent AST using league/commonmark with a
 * set of custom directive/token/component parsers.
 */
final class MarkdownDocumentParser implements DocumentParser
{
    private MarkdownParser $parser;

    public function __construct()
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        // Pin the Symfony YAML parser: when ext-yaml is loaded, CommonMark
        // silently switches to libyaml, which parses differently.
        $environment->addExtension(new FrontMatterExtension(new SymfonyYamlFrontMatterParser));
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new TaskListExtension);
        $environment->addExtension(new AutolinkExtension);

        // Component tags must win over CommonMark's raw-HTML block handling.
        $environment->addBlockStartParser(new ComponentBlockStartParser, 200);
        $environment->addBlockStartParser(new DirectiveBlockStartParser, 100);
        $environment->addInlineParser(new TokenInlineParser, 100);

        $this->parser = new MarkdownParser($environment);
    }

    public function parse(string $content): Document
    {
        $document = $this->parser->parse(TokenSyntax::normalize($content));

        $rawFrontMatter = $document->data->has('front_matter')
            ? $document->data->get('front_matter')
            : null;

        $frontMatter = new FrontMatter(is_array($rawFrontMatter) ? TokenSyntax::restoreDeep($rawFrontMatter) : []);

        return (new AstConverter)->convert($document, $frontMatter);
    }
}
