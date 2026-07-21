<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

/**
 * A single static-analysis rule run against a documentation tree. Each check is
 * self-contained: given the shared {@see CheckContext}, it yields the problems
 * it found as {@see Issue} objects.
 *
 * @internal
 */
interface Check
{
    /**
     * @return iterable<Issue>
     */
    public function run(CheckContext $context): iterable;
}
