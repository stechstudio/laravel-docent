<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

/**
 * Parses the attribute portion of a directive or component tag:
 * `key="value" key2="value2"` plus an optional leading bare shorthand token
 * (e.g. `:::when advanced-exports`).
 */
final class AttributeParser
{
    /**
     * @return array{attributes: array<string, string>, shorthand: ?string}
     */
    public static function parse(string $input): array
    {
        $input = trim($input);

        $attributes = [];
        if (preg_match_all('/([A-Za-z_][A-Za-z0-9_-]*)\s*=\s*"([^"]*)"/', $input, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $match[2];
            }
        }

        // A shorthand is a leading bare token with no `=` (e.g. `advanced-exports`).
        $shorthand = null;
        if (preg_match('/^([^\s"=]+)(?:\s|$)/', $input, $m) === 1 && ! str_contains($m[1], '=')) {
            $shorthand = $m[1];
        }

        return ['attributes' => $attributes, 'shorthand' => $shorthand];
    }
}
