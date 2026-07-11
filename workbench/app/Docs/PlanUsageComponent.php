<?php

namespace Workbench\App\Docs;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use STS\Docent\Runtime\Contracts\DocumentationComponent;
use STS\Docent\Runtime\DocumentationContext;

/**
 * A small Blade-rendered usage box, wired into the docs as
 * `<docs-component name="plan-usage" plan="team" />`.
 */
final class PlanUsageComponent implements DocumentationComponent
{
    public function render(DocumentationContext $context, array $attributes): Htmlable|string
    {
        $plan = $attributes['plan'] ?? 'starter';
        $used = 7_450;
        $limit = 10_000;

        return new HtmlString(Blade::render(<<<'BLADE'
            <div class="docent-component plan-usage" data-plan="{{ $plan }}">
                <strong>{{ ucfirst($plan) }} plan usage</strong>
                <p>{{ number_format($used) }} of {{ number_format($limit) }} transactions used this period.</p>
            </div>
            BLADE, ['plan' => $plan, 'used' => $used, 'limit' => $limit]));
    }
}
