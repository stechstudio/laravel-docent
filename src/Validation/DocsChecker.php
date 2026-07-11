<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

use STS\Docent\Validation\Checks\BrokenLinkCheck;
use STS\Docent\Validation\Checks\DuplicateSlugCheck;
use STS\Docent\Validation\Checks\FrontMatterCheck;
use STS\Docent\Validation\Checks\HeadingHierarchyCheck;
use STS\Docent\Validation\Checks\IncludeCycleCheck;
use STS\Docent\Validation\Checks\MissingImageCheck;
use STS\Docent\Validation\Checks\MissingIncludeCheck;
use STS\Docent\Validation\Checks\UnknownAbilityCheck;
use STS\Docent\Validation\Checks\UnknownAudienceCheck;
use STS\Docent\Validation\Checks\UnknownComponentCheck;
use STS\Docent\Validation\Checks\UnknownConditionCheck;
use STS\Docent\Validation\Checks\UnknownIconCheck;
use STS\Docent\Validation\Checks\UnknownLinkCheck;
use STS\Docent\Validation\Checks\UnknownRouteCheck;
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
            new HeadingHierarchyCheck,
            new UnknownIconCheck,
        ]);
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
