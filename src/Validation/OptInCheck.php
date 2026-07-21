<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

/**
 * A quality/style check that is OFF by default and runs only when its rule id is
 * enabled in `docent.check.rules` (to `error`, `warning`, or `warn`). Correctness
 * checks implement {@see Check} directly and always run; opt-in checks add this
 * marker so `docent:check` can skip them unless the site opts in.
 *
 * @internal
 */
interface OptInCheck extends Check
{
    /** The stable rule id this check emits (matches the Issue `check` slug). */
    public function rule(): string;
}
