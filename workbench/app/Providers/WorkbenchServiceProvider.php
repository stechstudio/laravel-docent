<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use STS\Docent\Facades\Docent;
use STS\Docent\Runtime\DocumentationContext;
use Workbench\App\Docs\PlanUsageComponent;
use Workbench\App\Models\User;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'docent.name' => 'Acme Ledger Docs',
            'docent.filesystem.path' => dirname(__DIR__, 2).'/resources/docs',
        ]);
    }

    public function boot(): void
    {
        // Demo gates: only the account owner may manage billing or view reports.
        Gate::define('billing.manage', fn (?User $user) => $user?->email === 'admin@acme.test');
        Gate::define('reports.view', fn (?User $user) => $user?->email === 'admin@acme.test');

        // Teach Docent about this application.
        Docent::value('account.plan', fn () => 'Team Plan', label: 'Account plan name')
            ->link('billing.settings', fn () => route('workbench.billing.settings'), label: 'Billing settings')
            ->component('plan-usage', PlanUsageComponent::class, label: 'Plan usage box')
            ->condition('beta-features', fn (DocumentationContext $context) => (bool) session('beta', false), label: 'Beta program');
    }
}
