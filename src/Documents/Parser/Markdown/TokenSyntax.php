<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\AppLinkKind;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Ast\Node;

/**
 * Parses `{{ kind:key arg1 arg2 }}` inline tokens into Docent AST nodes.
 *
 * CommonMark rejects link destinations that contain spaces, so before parsing
 * we normalize the interior whitespace of every recognized token to a sentinel
 * character ({@see self::SEP}). This lets a token be used both inline and as a
 * markdown link destination (`[x]({{ link:y }})`). Parsing then treats the
 * sentinel (and any residual whitespace) as the separator.
 */
final class TokenSyntax
{
    /** Interior token separator (ASCII unit separator; never appears in docs). */
    public const SEP = "\x1F";

    /**
     * Partial regex (no delimiters/anchors) for use as a CommonMark inline match definition.
     */
    public const PARTIAL = '\{\{[\s\x1F]*(value|link|route)[\s\x1F]*:[\s\x1F]*([^\s\x1F{}]+)((?:[\s\x1F]+[^\s\x1F{}]+)*)[\s\x1F]*\}\}';

    /**
     * Collapse interior whitespace of every recognized token to the sentinel so
     * the token survives CommonMark's link-destination parsing.
     */
    public static function normalize(string $content): string
    {
        return preg_replace_callback(
            '/\{\{[ \t]*(?:value|link|route)[ \t]*:[^}\n]*\}\}/',
            static fn (array $m): string => preg_replace('/[ \t]+/', self::SEP, $m[0]) ?? $m[0],
            $content,
        ) ?? $content;
    }

    /**
     * Restore sentinel separators to plain spaces. Applied to every literal
     * string captured off the CommonMark tree (code blocks, inline code, text,
     * raw HTML, attributes) so unparsed token syntax survives verbatim.
     */
    public static function restore(?string $content): ?string
    {
        return $content === null ? null : str_replace(self::SEP, ' ', $content);
    }

    /**
     * Recursively restore sentinel separators within parsed front matter values.
     */
    public static function restoreDeep(mixed $value): mixed
    {
        return match (true) {
            is_string($value) => self::restore($value),
            is_array($value) => array_map(self::restoreDeep(...), $value),
            default => $value,
        };
    }

    /**
     * Parse a complete token string (e.g. a markdown link destination) into a node.
     */
    public static function parse(string $token, ?int $line = null): ?Node
    {
        if (preg_match('/^'.self::PARTIAL.'$/', trim($token), $m) !== 1) {
            return null;
        }

        return self::fromMatch($m[1], $m[2], $m[3], $line);
    }

    /**
     * Build a node from already-captured regex parts.
     */
    public static function fromMatch(string $kind, string $key, string $argString, ?int $line = null): Node
    {
        $arguments = self::arguments($argString);

        return match (strtolower($kind)) {
            'value' => new DynamicValue($key, $arguments, $line),
            'link' => new AppLink(AppLinkKind::Link, $key, $arguments, $line),
            'route' => new AppLink(AppLinkKind::Route, $key, $arguments, $line),
            default => throw new \InvalidArgumentException('Unknown token kind: '.$kind),
        };
    }

    /**
     * @return list<string>
     */
    private static function arguments(string $argString): array
    {
        $argString = trim($argString, self::SEP." \t");

        if ($argString === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/[\s\x1F]+/', $argString) ?: []));
    }
}
