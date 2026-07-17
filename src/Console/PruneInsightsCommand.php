<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use LogicException;
use STS\Docent\Insights\InsightRecorder;
use STS\Docent\Sites\SiteRegistry;

final class PruneInsightsCommand extends Command
{
    protected $signature = 'docent:insights:prune
        {--days= : Override the configured retention window}
        {--site= : Prune only the selected Docent site}';

    protected $description = 'Delete Docent insight events older than the configured retention window';

    public function handle(SiteRegistry $sites): int
    {
        $days = $this->option('days');
        $retention = $days === null ? null : max(1, (int) $days);
        $keys = $this->selectedSites($sites);

        if ($keys === null) {
            return self::FAILURE;
        }

        $deleted = 0;

        foreach ($keys as $key) {
            $insights = $sites->serviceFor($key, InsightRecorder::class);

            if (! $insights instanceof InsightRecorder) {
                throw new LogicException("The Docent site [{$key}] did not provide an insight recorder.");
            }

            $deleted += $insights->prune($retention);
        }

        $this->components->info("Pruned {$deleted} Docent insight event".($deleted === 1 ? '.' : 's.'));

        return self::SUCCESS;
    }

    /** @return null|list<string> */
    private function selectedSites(SiteRegistry $sites): ?array
    {
        $selected = $this->option('site');
        $keys = $sites->keys();

        if ($selected === null) {
            return $keys;
        }

        if (! in_array($selected, $keys, true)) {
            $this->components->error('Unknown Docent site ['.$selected.'].');

            return null;
        }

        return [$selected];
    }
}
