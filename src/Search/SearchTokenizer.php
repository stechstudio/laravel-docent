<?php

declare(strict_types=1);

namespace STS\Docent\Search;

final class SearchTokenizer
{
    /** @return list<string> */
    public static function tokenize(string $text): array
    {
        if ($text === '' || preg_match_all("/[\\p{L}\\p{N}]+(?:['’][\\p{L}\\p{N}]+)?/u", mb_strtolower($text), $matches) === 0) {
            return [];
        }

        return array_map(static function (string $term): string {
            return preg_replace("/['’]s$/u", '', $term) ?? $term;
        }, $matches[0]);
    }

    public static function stem(string $term): string
    {
        $length = mb_strlen($term);

        if ($length > 4 && str_ends_with($term, 'ies')) {
            return mb_substr($term, 0, -3).'y';
        }

        if ($length > 4 && preg_match('/(?:sses|xes|zes|ches|shes)$/u', $term) === 1) {
            return mb_substr($term, 0, -2);
        }

        if ($length > 3 && str_ends_with($term, 's')
            && ! str_ends_with($term, 'ss')
            && ! str_ends_with($term, 'us')
            && ! str_ends_with($term, 'is')) {
            return mb_substr($term, 0, -1);
        }

        if ($length > 7 && str_ends_with($term, 'ing')) {
            return self::undouble(mb_substr($term, 0, -3));
        }

        if ($length > 6 && str_ends_with($term, 'ed')) {
            return self::undouble(mb_substr($term, 0, -2));
        }

        return $term;
    }

    private static function undouble(string $term): string
    {
        if (preg_match('/([^aeiou])\1$/u', $term, $matches) !== 1) {
            return $term;
        }

        return mb_substr($term, 0, -1);
    }
}
