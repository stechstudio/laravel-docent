<?php

declare(strict_types=1);

namespace STS\Docent\Validation\Checks;

use STS\Docent\Validation\Check;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\Issue;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Validates each page's YAML front matter: it must parse, and it should declare
 * a `title`. Parsing raw content we don't control is a legitimate try/catch
 * boundary — a malformed block becomes a loud error rather than a fatal.
 */
final class FrontMatterCheck implements Check
{
    public function run(CheckContext $context): iterable
    {
        foreach ($context->pages() as $page) {
            $source = $context->source($page->slug);

            if ($source === null) {
                continue;
            }

            yield from $this->inspect($page->slug, $source->rawContent);
        }
    }

    /**
     * @return iterable<Issue>
     */
    private function inspect(string $slug, string $content): iterable
    {
        if (! str_starts_with($content, '---')
            || preg_match('/^---\R(.*?)\R---\s*(?:\R|$)/s', $content, $matches) !== 1) {
            yield Issue::warning('missing-title', $slug, 'Page has no front matter; a `title` is recommended.', 1);

            return;
        }

        try {
            $data = Yaml::parse($matches[1]);
        } catch (ParseException $e) {
            yield Issue::error('front-matter', $slug, 'Invalid YAML front matter: '.$e->getMessage(), $e->getParsedLine() + 1);

            return;
        }

        if (! is_array($data) || ! isset($data['title']) || (is_string($data['title']) && trim($data['title']) === '')) {
            yield Issue::warning('missing-title', $slug, 'Page is missing a `title` in its front matter.', 1);
        }

        if (! is_array($data)) {
            return;
        }

        $keywords = is_array($data['search'] ?? null) ? ($data['search']['keywords'] ?? null) : null;

        if ($keywords === null) {
            return;
        }

        if (! is_array($keywords) || ! array_is_list($keywords)) {
            yield Issue::error('search-keywords', $slug, '`search.keywords` must be a YAML list of strings.', 1);

            return;
        }

        if (count($keywords) > 12) {
            yield Issue::error('search-keywords', $slug, '`search.keywords` accepts at most 12 entries.', 1);
        }

        foreach ($keywords as $keyword) {
            if (! is_string($keyword) || trim($keyword) === '' || mb_strlen(trim($keyword)) > 80) {
                yield Issue::error('search-keywords', $slug, 'Each `search.keywords` entry must be a non-empty string of at most 80 characters.', 1);
                break;
            }
        }
    }
}
