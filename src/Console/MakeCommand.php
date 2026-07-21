<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use STS\Docent\Content\ContentType;
use STS\Docent\Sites\SiteRegistry;

/**
 * Scaffolds a new documentation page from a Diátaxis content-type template —
 * tutorial, how-to, reference, or concept — writing valid Docent dialect the
 * author (or a coding agent) fills in.
 */
final class MakeCommand extends Command
{
    protected $signature = 'docent:make
        {type : Content type: tutorial, how-to, reference, or concept}
        {slug : Page slug relative to the docs root, e.g. billing/refunds}
        {--site= : Scaffold into the selected Docent site}
        {--force : Overwrite an existing page}';

    protected $description = 'Scaffold a documentation page from a content-type template';

    public function handle(SiteRegistry $sites): int
    {
        $type = ContentType::tryFrom((string) $this->argument('type'));

        if ($type === null) {
            $this->components->error('Unknown content type. Use one of: '.implode(', ', ContentType::values()).'.');

            return self::FAILURE;
        }

        $selected = $this->option('site');

        if ($selected !== null && ! $sites->has($selected)) {
            $this->components->error('Unknown Docent site ['.$selected.'].');

            return self::FAILURE;
        }

        $docent = $sites->site($selected ?? $sites->defaultKey());
        $docs = (string) ($docent->config('filesystem.path') ?? resource_path('docs'));

        $slug = trim((string) $this->argument('slug'), '/');
        $slug = Str::endsWith($slug, '.md') ? Str::beforeLast($slug, '.md') : $slug;

        if ($slug === '') {
            $this->components->error('A slug is required.');

            return self::FAILURE;
        }

        $path = $docs.'/'.$slug.'.md';

        if (File::exists($path) && ! (bool) $this->option('force')) {
            $this->components->error($path.' already exists. Pass --force to overwrite.');

            return self::FAILURE;
        }

        $title = Str::headline(Str::afterLast($slug, '/'));

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $type->scaffold($title)."\n");

        $this->components->twoColumnDetail($path, '<fg=green>created</>');
        $this->components->info('Scaffolded a '.$type->value.' page. Fill it in, then run `php artisan docent:check`.');

        return self::SUCCESS;
    }
}
