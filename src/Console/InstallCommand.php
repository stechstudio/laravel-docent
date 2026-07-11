<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Publishes the config and scaffolds starter documentation. Idempotent — never
 * overwrites files that already exist.
 */
final class InstallCommand extends Command
{
    protected $signature = 'docent:install';

    protected $description = 'Install Docent: publish config and scaffold starter docs';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'docent-config']);

        $docs = config('docent.filesystem.path') ?? resource_path('docs');

        $this->scaffold($docs.'/index.md', $this->indexStub());
        $this->scaffold($docs.'/getting-started/introduction.md', $this->introductionStub());

        $this->newLine();
        $this->components->info('Docent installed.');
        $this->components->bulletList([
            'Write your docs in '.$docs,
            'Register app integrations (values, links, components) in a service provider via the Docent facade',
            'Visit /'.config('docent.route.prefix', 'docs').' to browse',
        ]);

        return self::SUCCESS;
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

        # Welcome to your documentation

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

        # Introduction

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
