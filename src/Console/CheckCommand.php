<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use LogicException;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Sites\SiteRegistry;
use STS\Docent\Validation\CheckContext;
use STS\Docent\Validation\DocsChecker;
use STS\Docent\Validation\Issue;
use STS\Docent\Validation\Severity;

/**
 * Runs Docent's static validation suite over the documentation tree, grouping
 * problems by page. Exits non-zero when errors are found (or, with `--strict`,
 * when any warnings are found).
 */
final class CheckCommand extends Command
{
    protected $signature = 'docent:check
        {--strict : Treat warnings as failures}
        {--site= : Check only the selected Docent site}';

    protected $description = 'Statically validate the documentation tree';

    public function handle(
        DocumentParser $parser,
        SiteRegistry $sites,
    ): int {
        /** @var array<string, mixed> $config */
        $config = (array) $this->laravel['config']->get('docent', []);
        $issues = DocsChecker::siteDefinitions($config);
        $keys = $this->selectedSites($config);

        if ($keys === null) {
            return self::FAILURE;
        }

        $pages = 0;

        if ($this->count($issues, Severity::Error) === 0) {
            foreach ($keys as $key) {
                $docent = $sites->site($key);
                $repository = $sites->serviceFor($key, DocumentationRepository::class);

                if (! $repository instanceof DocumentationRepository) {
                    throw new LogicException("The Docent site [{$key}] did not provide a documentation repository.");
                }

                $context = new CheckContext(
                    repository: $repository,
                    parser: $parser,
                    registry: $docent->registry(),
                    docsPath: (string) ($docent->config('filesystem.path') ?? resource_path('docs')),
                    publicPath: public_path(),
                    routePrefix: (string) $docent->config('route.prefix', 'docs'),
                    routeExists: static fn (string $name): bool => Route::has($name),
                    abilityExists: static fn (string $ability): bool => Gate::has($ability),
                    docent: $docent,
                );

                $issues = [...$issues, ...DocsChecker::withDefaults()->run($context)];
                $pages += count($context->pages());
            }
        }

        $errors = $this->count($issues, Severity::Error);
        $warnings = $this->count($issues, Severity::Warning);

        if ($issues === []) {
            $this->components->info('Docent looks great — no problems found in '.$pages.' '.$this->pluralize('page', $pages).'.');

            return self::SUCCESS;
        }

        $this->render($issues);
        $this->summary($errors, $warnings);

        $strict = (bool) $this->option('strict');

        return $errors > 0 || ($strict && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return null|list<string>
     */
    private function selectedSites(array $config): ?array
    {
        $configured = $config['sites'] ?? [];
        $configured = is_array($configured) ? $configured : [];
        $keys = [];

        foreach (array_keys($configured) as $key) {
            $key = (string) $key;

            if (preg_match(SiteRegistry::KEY_PATTERN, $key) === 1) {
                $keys[] = $key;
            }
        }

        $selected = $this->option('site');

        if ($selected === null) {
            return $keys;
        }

        if (! in_array($selected, $keys, true)) {
            $this->components->error('Unknown Docent site ['.$selected.'].');

            return null;
        }

        return [$selected];
    }

    /**
     * @param  list<Issue>  $issues
     */
    private function render(array $issues): void
    {
        $this->newLine();

        foreach ($this->groupBySlug($issues) as $slug => $pageIssues) {
            $this->line('  <options=bold>'.($slug === '' ? '(home)' : $slug).'</>');

            foreach ($pageIssues as $issue) {
                $this->line('    '.$this->badge($issue->severity).'  '.$this->reference($issue).'  '.$issue->message);
            }

            $this->newLine();
        }
    }

    /**
     * @param  list<Issue>  $issues
     * @return array<string, list<Issue>>
     */
    private function groupBySlug(array $issues): array
    {
        $grouped = [];

        foreach ($issues as $issue) {
            $grouped[$issue->slug][] = $issue;
        }

        ksort($grouped);

        foreach ($grouped as &$pageIssues) {
            usort($pageIssues, static fn (Issue $a, Issue $b): int => ($a->line ?? 0) <=> ($b->line ?? 0));
        }

        return $grouped;
    }

    private function reference(Issue $issue): string
    {
        $location = $issue->slug === '' ? '(home)' : $issue->slug;
        $location .= $issue->line !== null ? ':'.$issue->line : '';

        return '<fg=gray>'.$location.'</> <fg=cyan>'.$issue->check.'</>';
    }

    private function badge(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => '<fg=red;options=bold>error  </>',
            Severity::Warning => '<fg=yellow;options=bold>warning</>',
        };
    }

    private function summary(int $errors, int $warnings): void
    {
        $parts = [];

        if ($errors > 0) {
            $parts[] = '<fg=red;options=bold>'.$errors.' '.$this->pluralize('error', $errors).'</>';
        }

        if ($warnings > 0) {
            $parts[] = '<fg=yellow;options=bold>'.$warnings.' '.$this->pluralize('warning', $warnings).'</>';
        }

        $this->line('  Found '.implode(', ', $parts).'.');
        $this->newLine();
    }

    private function pluralize(string $word, int $count): string
    {
        return $count === 1 ? $word : $word.'s';
    }

    /**
     * @param  list<Issue>  $issues
     */
    private function count(array $issues, Severity $severity): int
    {
        return count(array_filter($issues, static fn (Issue $issue): bool => $issue->severity === $severity));
    }
}
