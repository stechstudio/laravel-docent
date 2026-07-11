<?php

declare(strict_types=1);

namespace STS\Docent\Tests\Support;

use Illuminate\Contracts\Support\Htmlable;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;

/**
 * A trivial component used to prove component resolution through the container.
 */
final class PlanUsageComponent implements DocumentationComponent
{
    public function render(DocumentationContext $context, array $attributes): Htmlable|string
    {
        $plan = $attributes['plan'] ?? 'free';

        return '<div class="plan-usage" data-plan="'.e($plan).'">Usage for the '.e($plan).' plan.</div>';
    }
}
