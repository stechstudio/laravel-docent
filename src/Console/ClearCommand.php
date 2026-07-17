<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use LogicException;
use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Support\DocentCache;

/**
 * Invalidates all Docent cache entries (parsed ASTs, navigation, search) by
 * bumping the version stamp folded into every key.
 */
final class ClearCommand extends Command
{
    protected $signature = 'docent:clear {--site= : Clear only the selected Docent site}';

    protected $description = 'Clear cached Docent ASTs, navigation, and search index';

    public function handle(SiteRegistry $sites): int
    {
        $keys = $this->selectedSites($sites);

        if ($keys === null) {
            return self::FAILURE;
        }

        foreach ($keys as $key) {
            $cache = $sites->serviceFor($key, DocentCache::class);

            if (! $cache instanceof DocentCache) {
                throw new LogicException("The Docent site [{$key}] did not provide a cache service.");
            }

            $cache->bump();
        }

        $this->components->info('Docent cache cleared for '.count($keys).' '.$this->pluralize('site', count($keys)).'.');

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

    private function pluralize(string $word, int $count): string
    {
        return $count === 1 ? $word : $word.'s';
    }
}
