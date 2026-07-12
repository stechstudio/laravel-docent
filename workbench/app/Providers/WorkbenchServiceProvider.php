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
            // Demo the composite store: database pages compose over the files.
            'docent.database.enabled' => true,
            // Demo the admin panel (gated below to the account owner).
            'docent.admin.enabled' => true,
            // Dogfood the same-origin help widget on the demo dashboard.
            'docent.widget.enabled' => true,
        ]);

        // Try a different feel — theming tokens are pure config, no rebuild:
        // config([
        //     'docent.theme.accent' => '#e11d48',
        //     'docent.theme.gray' => 'zinc',
        //     'docent.theme.radius' => 'soft',
        //     'docent.theme.font.sans' => "'Inter', ui-sans-serif, system-ui, sans-serif",
        //     'docent.theme.font.href' => 'https://fonts.bunny.net/css?family=inter:400,500,600,700',
        // ]);
    }

    public function boot(): void
    {
        // The database store is opt-in, so its migrations load only for the demo.
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');

        // Demo gates: only the account owner may manage billing or view reports.
        Gate::define('billing.manage', fn (?User $user) => $user?->email === 'admin@acme.test');
        Gate::define('reports.view', fn (?User $user) => $user?->email === 'admin@acme.test');

        // The admin panel is gated to the account owner; guests are denied.
        Gate::define('viewDocentAdmin', fn (?User $user) => $user?->email === 'admin@acme.test');

        // Teach Docent about this application.
        Docent::value('account.plan', fn () => 'Team Plan', label: 'Account plan name')
            ->link('billing.settings', fn () => route('workbench.billing.settings'), label: 'Billing settings')
            ->component('plan-usage', PlanUsageComponent::class, label: 'Plan usage box')
            ->condition('beta-features', fn (DocumentationContext $context) => (bool) session('beta', false), label: 'Beta program')
            ->suggest('dashboard.*', ['getting-started/quickstart', 'billing/payment-methods']);
    }
}
