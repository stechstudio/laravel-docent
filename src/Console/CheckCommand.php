<?php

declare(strict_types=1);

namespace STS\Docent\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use STS\Docent\Content\Repositories\DocumentationRepository;
use STS\Docent\Documents\Parser\DocumentParser;
use STS\Docent\Runtime\IntegrationRegistry;
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
    protected $signature = 'docent:check {--strict : Treat warnings as failures}';

    protected $description = 'Statically validate the documentation tree';

    public function handle(
        DocumentationRepository $repository,
        DocumentParser $parser,
        IntegrationRegistry $registry,
    ): int {
        $context = new CheckContext(
            repository: $repository,
            parser: $parser,
            registry: $registry,
            docsPath: (string) (config('docent.filesystem.path') ?? resource_path('docs')),
            publicPath: public_path(),
            routePrefix: (string) config('docent.route.prefix', 'docs'),
            routeExists: static fn (string $name): bool => Route::has($name),
            abilityExists: static fn (string $ability): bool => Gate::has($ability),
        );

        $issues = DocsChecker::withDefaults()->run($context);

        $errors = $this->count($issues, Severity::Error);
        $warnings = $this->count($issues, Severity::Warning);

        if ($issues === []) {
            $this->components->info('Docent looks great — no problems found in '.count($context->pages()).' pages.');

            return self::SUCCESS;
        }

        $this->render($issues);
        $this->summary($errors, $warnings);

        $strict = (bool) $this->option('strict');

        return $errors > 0 || ($strict && $warnings > 0) ? self::FAILURE : self::SUCCESS;
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
