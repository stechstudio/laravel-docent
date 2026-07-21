<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use STS\Docent\Sites\SiteRegistry;

/**
 * Publishes the config and scaffolds starter documentation. Idempotent — never
 * overwrites files that already exist.
 */
final class InstallCommand extends Command
{
    protected $signature = 'docent:install {--with-database : Also publish the database store migrations}';

    protected $description = 'Install Docent: publish config and scaffold starter docs';

    public function handle(SiteRegistry $sites): int
    {
        $docent = $sites->site($sites->defaultKey());
        $this->call('vendor:publish', ['--tag' => 'docent-config']);

        $docs = $docent->config('filesystem.path') ?? resource_path('docs');

        $this->scaffold($docs.'/index.md', $this->indexStub());
        $this->scaffold($docs.'/getting-started/introduction.md', $this->introductionStub());
        $this->writeAgentPointer((string) $docs);

        $withDatabase = (bool) $this->option('with-database');

        if ($withDatabase) {
            $this->call('vendor:publish', ['--tag' => 'docent-migrations']);
        }

        $this->newLine();
        $this->components->info('Docent installed.');
        $this->components->bulletList([
            'Write your docs in '.$docs.', or scaffold one with `php artisan docent:make how-to <slug>`',
            'Register app integrations (values, links, components) in a service provider via the Docent facade',
            'Validate anytime with `php artisan docent:check` (add `--format=json` for tooling)',
            'Told your coding agent about Docent in AGENTS.md — it will run docent:guide before writing docs',
            'Visit /'.$docent->config('route.prefix', 'docs').' to browse',
            ...$withDatabase ? [
                'Run `php artisan migrate` to create the docent_pages tables',
                'Set `docent.database.enabled` to true to compose the database store over your files',
            ] : [],
        ]);

        return self::SUCCESS;
    }

    private function writeAgentPointer(string $docsPath): void
    {
        $target = File::exists(base_path('AGENTS.md'))
            ? base_path('AGENTS.md')
            : (File::exists(base_path('CLAUDE.md')) ? base_path('CLAUDE.md') : base_path('AGENTS.md'));

        $rel = Str::startsWith($docsPath, base_path())
            ? ltrim(Str::after($docsPath, base_path()), DIRECTORY_SEPARATOR)
            : $docsPath;

        $block = "<!-- docent:guide start -->\n"
            ."## Documentation (Docent)\n\n"
            .'Docs live in `'.$rel."`. Before writing or editing docs, run\n"
            ."`php artisan docent:guide` to get the authoring dialect and this app's\n"
            ."registered values, links, conditions, audiences, and components. Validate\n"
            ."changes with `php artisan docent:check` and fix everything it reports.\n"
            .'<!-- docent:guide end -->';

        $existing = File::exists($target) ? File::get($target) : null;
        $status = 'created';

        if ($existing === null) {
            $contents = $block."\n";
        } elseif (str_contains($existing, '<!-- docent:guide start -->')) {
            $contents = (string) preg_replace(
                '/<!-- docent:guide start -->.*?<!-- docent:guide end -->/s',
                $block,
                $existing,
            );
            $status = 'updated';
        } else {
            $contents = rtrim($existing)."\n\n".$block."\n";
            $status = 'updated';
        }

        File::put($target, $contents);
        $this->components->twoColumnDetail($target, '<fg=green>'.$status.'</>');
    }

    private function scaffold(string $path, string $contents): void
    {
        if (File::exists($path)) {
            $this->components->twoColumnDetail($path, '<fg=yellow>exists</>');

            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);

        $this->components->twoColumnDetail($path, '<fg=green>created</>');
    }

    private function indexStub(): string
    {
        return <<<'MD'
        ---
        title: Welcome
        description: Start here.
        order: 0
        ---

        This site is powered by [Docent](https://github.com/stechstudio/laravel-docent) —
        permission-aware documentation that participates in your Laravel application.

        :::tip title="You're all set"
        Edit `resources/docs/index.md` to make this page your own.
        :::

        Head over to [Getting Started](getting-started/introduction) to learn the authoring syntax.
        MD;
    }

    private function introductionStub(): string
    {
        return <<<'MD'
        ---
        title: Introduction
        description: Learn how to author Docent pages.
        order: 1
        ---

        Docent pages are Markdown with a little extra: front matter, callouts,
        permission-aware blocks, and dynamic values pulled from your app.

        ## Front matter

        Every page opens with a YAML front matter block for its `title`,
        `description`, navigation `order`, and optional `authorize` gate.

        ## Callouts

        :::note
        Use callouts to draw attention to important details.
        :::

        ## Code blocks

        ```php
        use STS\Docent\Facades\Docent;

        Docent::value('account.plan', fn () => auth()->user()->plan->name);
        ```
        MD;
    }
}
