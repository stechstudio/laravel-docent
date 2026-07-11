<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Parser\Markdown;

/**
 * GitHub-style heading slugger with per-document de-duplication.
 */
final class Slugger
{
    /** @var array<string, int> */
    private array $seen = [];

    public function slug(string $text): string
    {
        $slug = strtolower(trim($text));
        // Drop anything that isn't a word char, space or hyphen (GitHub keeps unicode word chars).
        $slug = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $slug) ?? '';
        $slug = preg_replace('/\s+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'section';
        }

        if (! isset($this->seen[$slug])) {
            $this->seen[$slug] = 0;

            return $slug;
        }

        $suffix = ++$this->seen[$slug];

        return $slug.'-'.$suffix;
    }
}
