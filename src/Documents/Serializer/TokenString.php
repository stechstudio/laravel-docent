<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Serializer;

use STS\Docent\Documents\Ast\AppLink;
use STS\Docent\Documents\Ast\DynamicValue;

/**
 * Formats Docent inline tokens back into their canonical `{{ kind:key args }}`
 * spelling. Shared by the markdown exporter (which writes them literally) and
 * {@see AstToTiptap} (which uses the same string as the `href` of a `link` mark
 * when a markdown link's destination is an {@see AppLink}).
 */
final class TokenString
{
    public static function value(DynamicValue $value): string
    {
        return self::format('value', $value->key, $value->arguments);
    }

    public static function appLink(AppLink $link): string
    {
        return self::format($link->kind->value, $link->key, $link->parameters);
    }

    /**
     * @param  list<string>  $arguments
     */
    private static function format(string $kind, string $key, array $arguments): string
    {
        $body = $arguments === [] ? $key : $key.' '.implode(' ', $arguments);

        return '{{ '.$kind.':'.$body.' }}';
    }
}
