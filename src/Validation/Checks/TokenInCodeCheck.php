<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Ast\Node;
use STS\Docent\Documents\Parser\Markdown\TokenSyntax;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags token syntax sitting inside code spans and code blocks, where it renders
 * verbatim rather than resolving.
 *
 * Leaving tokens untouched inside code is deliberate — {@see TokenSyntax::restore()}
 * — and it is what lets the dialect document itself. The cost is that such a
 * token never becomes an AST node, so the reference checks that walk for
 * {@see DynamicValue} and {@see AppLink} are structurally blind to it. A page
 * can ship mustache syntax where a value belonged, render 200, and pass every
 * other check.
 *
 * Registry membership is what keeps this quiet: a generic example
 * (`{{ value:some.key }}`) names nothing real and is ignored, while a token
 * naming a value, link, or route this application actually resolves is almost
 * always a mistake rather than an illustration.
 */
final class TokenInCodeCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $document = $context->document($page->slug);

            if ($document === null) {
                continue;
            }

            foreach (AstWalker::walk($document) as $node) {
                $code = match (true) {
                    $node instanceof InlineCode, $node instanceof CodeBlock => $node->code,
                    default => null,
                };

                if ($code === null) {
                    continue;
                }

                yield from $this->issues($code, $node, $page->slug, $context);
            }
        }
    }

    /**
     * @return iterable<Issue>
     */
    private function issues(string $code, Node $node, string $slug, CheckContext $context): iterable
    {
        if (preg_match_all('/'.TokenSyntax::PARTIAL.'/', $code, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return;
        }

        foreach ($matches[0] as $index => [$token, $offset]) {
            $kind = strtolower($matches[1][$index][0]);
            $key = $matches[2][$index][0];

            if (! $this->registered($kind, $key, $context)) {
                continue;
            }

            yield Issue::warning(
                'token-in-code',
                $slug,
                'Registered token "{{ '.$kind.':'.$key.' }}" sits inside code and will render verbatim.',
                $this->line($node, $code, $offset),
            );
        }
    }

    private function registered(string $kind, string $key, CheckContext $context): bool
    {
        return match ($kind) {
            'value' => $context->registry()->hasValue($key),
            'link' => $context->registry()->hasLink($key),
            'route' => $context->routeExists($key),
            default => false,
        };
    }

    /**
     * A code block spans many lines, so point at the line the token is actually
     * on rather than at the opening fence.
     */
    private function line(Node $node, string $code, int $offset): ?int
    {
        if ($node->line === null || ! $node instanceof CodeBlock) {
            return $node->line;
        }

        return $node->line + substr_count(substr($code, 0, $offset), "\n");
    }
}
