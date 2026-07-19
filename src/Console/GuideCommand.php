<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use STS\Docent\Sites\SiteRegistry;

/**
 * Prints the documentation-authoring reference plus this application's Docent
 * inventory (sites, content paths, and registered integrations) as Markdown —
 * a single entry point for a coding agent about to write or edit docs pages.
 */
final class GuideCommand extends Command
{
    protected $signature = 'docent:guide
        {--site= : Limit the inventory to one Docent site}';

    protected $description = 'Print the docs authoring reference and this app\'s Docent inventory';

    public function handle(SiteRegistry $sites): int
    {
        $selected = $this->option('site');

        if ($selected !== null && ! $sites->has($selected)) {
            $this->components->error('Unknown Docent site ['.$selected.'].');

            return self::FAILURE;
        }

        foreach (explode("\n", (string) file_get_contents(__DIR__.'/../../resources/guides/authoring.md')) as $line) {
            $this->line($line);
        }

        $this->line('# This application');

        foreach ($selected !== null ? [$selected] : $sites->keys() as $key) {
            $this->site($sites, $key);
        }

        $this->newLine();
        $this->line('Validate every change: `php artisan docent:check`'
            .($selected !== null ? ' (add `--site='.$selected.'`)' : '').'.');

        return self::SUCCESS;
    }

    private function site(SiteRegistry $sites, string $key): void
    {
        $config = $sites->siteConfig($key);
        $manager = $sites->site($key);
        $path = $config->get('filesystem.path');

        $this->newLine();
        $this->line('## Site: '.$key);
        $this->newLine();
        $this->line('- Name: '.$manager->siteName());
        $this->line('- Content directory: '.(is_string($path) ? $path : resource_path('docs')));
        $this->line('- Route prefix: /'.trim((string) $config->get('route.prefix', 'docs'), '/'));
        $this->line('- Database pages: '.((bool) $config->get('database.enabled', false) ? 'enabled' : 'disabled'));

        foreach ($sites->registryFor($key)->describe() as $kind => $entries) {
            if ($entries === []) {
                continue;
            }

            $this->newLine();
            $this->line('### Registered '.$kind);
            $this->newLine();

            foreach ($entries as $entry) {
                $label = $entry['label'] ?? null;
                $description = $entry['description'] ?? null;
                $suffix = array_filter([$label, $description], static fn (?string $text): bool => $text !== null && $text !== '');

                $this->line('- `'.$entry['name'].'`'.($suffix === [] ? '' : ' — '.implode('; ', $suffix)));
            }
        }
    }
}
