<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use Illuminate\Support\Str;
use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\InlineCode;
use STS\Docent\Documents\Parser\Markdown\TokenSyntax;
use STS\Docent\Validation\AstWalker;
use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;

/**
 * Flags token syntax sitting inside an inline code span, where it renders
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
 *
 * Fenced code blocks are deliberately out of scope. A block showing what to
 * write is *supposed* to contain literal dialect syntax, and naming a real
 * registered key is what makes such an example useful rather than suspicious —
 * so warning there would fire on correct documentation with no way to keep the
 * valuable inline case. An inline span in running prose has no such defense:
 * nobody writes `{{ value:account.plan }}` mid-sentence meaning the characters.
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
                if ($node instanceof InlineCode) {
                    yield from $this->issues($node, $page->slug, $context);
                }
            }
        }
    }

    /**
     * @return iterable<Issue>
     */
    private function issues(InlineCode $node, string $slug, CheckContext $context): iterable
    {
        preg_match_all('/'.TokenSyntax::PARTIAL.'/', $node->code, $matches, PREG_SET_ORDER);

        foreach ($matches as [$token, $kind, $key]) {
            if ($this->registered(strtolower($kind), $key, $context)) {
                yield Issue::warning(
                    'token-in-code',
                    $slug,
                    'Registered token "'.$this->readable($token).'" sits inside a code span and will render verbatim.',
                    $node->line,
                );
            }
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
     * The token as written, with the parser's separator sentinel restored and
     * whitespace collapsed — so two occurrences differing only in their
     * arguments stay distinguishable in the report.
     */
    private function readable(string $token): string
    {
        return Str::squish((string) TokenSyntax::restore($token));
    }
}
