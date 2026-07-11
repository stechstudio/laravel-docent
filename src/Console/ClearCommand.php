<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use STS\Docent\Support\DocentCache;

/**
 * Invalidates all Docent cache entries (parsed ASTs, navigation, search) by
 * bumping the version stamp folded into every key.
 */
final class ClearCommand extends Command
{
    protected $signature = 'docent:clear';

    protected $description = 'Clear cached Docent ASTs, navigation, and search index';

    public function handle(DocentCache $cache): int
    {
        $cache->bump();

        $this->components->info('Docent cache cleared.');

        return self::SUCCESS;
    }
}
