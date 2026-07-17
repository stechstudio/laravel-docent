<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

use STS\Docent\Validation\Checks\AiCorpusSizeCheck;
use STS\Docent\Validation\Checks\BrokenLinkCheck;
use STS\Docent\Validation\Checks\ContentComponentCheck;
use STS\Docent\Validation\Checks\DuplicateSlugCheck;
use STS\Docent\Validation\Checks\FrontMatterCheck;
use STS\Docent\Validation\Checks\HeadingHierarchyCheck;
use STS\Docent\Validation\Checks\IncludeCycleCheck;
use STS\Docent\Validation\Checks\LockedPageShadowedCheck;
use STS\Docent\Validation\Checks\MissingImageCheck;
use STS\Docent\Validation\Checks\MissingIncludeCheck;
use STS\Docent\Validation\Checks\NavigationLinkCheck;
use STS\Docent\Validation\Checks\NavigationSectionCheck;
use STS\Docent\Validation\Checks\RedirectCheck;
use STS\Docent\Validation\Checks\ShadowedPageCheck;
use STS\Docent\Validation\Checks\SiteDefinitionCheck;
use STS\Docent\Validation\Checks\UnknownAbilityCheck;
use STS\Docent\Validation\Checks\UnknownAudienceCheck;
use STS\Docent\Validation\Checks\UnknownComponentCheck;
use STS\Docent\Validation\Checks\UnknownConditionCheck;
use STS\Docent\Validation\Checks\UnknownIconCheck;
use STS\Docent\Validation\Checks\UnknownLinkCheck;
use STS\Docent\Validation\Checks\UnknownRouteCheck;
use STS\Docent\Validation\Checks\UnknownSuggestionCheck;
use STS\Docent\Validation\Checks\UnknownValueCheck;

/**
 * Runs the full suite of static checks over a documentation tree and returns
 * every {@see Issue} found.
 */
final class DocsChecker
{
    /**
     * @param  list<Check>  $checks
     */
    public function __construct(
        private readonly array $checks,
    ) {}

    public static function withDefaults(): self
    {
        return new self([
            new FrontMatterCheck,
            new RedirectCheck,
            new AiCorpusSizeCheck,
            new DuplicateSlugCheck,
            new BrokenLinkCheck,
            new UnknownConditionCheck,
            new UnknownValueCheck,
            new UnknownLinkCheck,
            new UnknownRouteCheck,
            new UnknownComponentCheck,
            new UnknownAudienceCheck,
            new UnknownAbilityCheck,
            new MissingIncludeCheck,
            new IncludeCycleCheck,
            new MissingImageCheck,
            new ContentComponentCheck,
            new HeadingHierarchyCheck,
            new UnknownIconCheck,
            new UnknownSuggestionCheck,
            new NavigationSectionCheck,
            new NavigationLinkCheck,
            new LockedPageShadowedCheck,
            new ShadowedPageCheck,
        ]);
    }

    /**
     * The subset of checks that validate a single page's references against the
     * registry and the live page tree — the ones worth running inline on every
     * admin save and preview. Tree-wide checks (duplicate slugs, shadowed pages,
     * missing local images) are excluded: they belong to `docent:check`, not a
     * per-draft edit.
     */
    public static function references(): self
    {
        return new self([
            new RedirectCheck,
            new BrokenLinkCheck,
            new UnknownConditionCheck,
            new UnknownValueCheck,
            new UnknownLinkCheck,
            new UnknownRouteCheck,
            new UnknownComponentCheck,
            new UnknownAudienceCheck,
            new UnknownAbilityCheck,
            new MissingIncludeCheck,
            new ContentComponentCheck,
            new UnknownIconCheck,
        ]);
    }

    /**
     * Validate the global site map once before any site-specific content checks.
     *
     * @param  array<string, mixed>  $config
     * @return list<Issue>
     */
    public static function siteDefinitions(array $config): array
    {
        return iterator_to_array((new SiteDefinitionCheck($config))->issues(), false);
    }

    /**
     * @return list<Issue>
     */
    public function run(CheckContext $context): array
    {
        $issues = [];

        foreach ($this->checks as $check) {
            foreach ($check->run($context) as $issue) {
                $issues[] = $issue;
            }
        }

        return $issues;
    }
}
