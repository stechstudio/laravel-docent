<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use STS\Docent\Insights\InsightRecorder;

final class PruneInsightsCommand extends Command
{
    protected $signature = 'docent:insights:prune {--days= : Override the configured retention window}';

    protected $description = 'Delete Docent insight events older than the configured retention window';

    public function handle(InsightRecorder $insights): int
    {
        $days = $this->option('days');
        $retention = $days === null ? null : max(1, (int) $days);
        $deleted = $insights->prune($retention);

        $this->components->info("Pruned {$deleted} Docent insight event".($deleted === 1 ? '.' : 's.'));

        return self::SUCCESS;
    }
}
