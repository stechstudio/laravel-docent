<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

/**
 * The site's `check.rules` overrides, applied wherever checks run.
 *
 * Both `docent:check` and the admin's per-draft validation resolve rules through
 * this, so a rule silenced with `'off'` is silent in the editor too and a
 * promoted severity shows up in both. Reading the config in one place is the
 * only thing keeping those two surfaces from drifting.
 *
 * @internal
 */
final class CheckRules
{
    /**
     * @param  array<array-key, mixed>  $rules
     */
    private function __construct(
        private readonly array $rules,
    ) {}

    /**
     * @param  mixed  $rules  The raw `docent.check.rules` value, whatever it turned out to be.
     */
    public static function from(mixed $rules): self
    {
        return new self(is_array($rules) ? $rules : []);
    }

    /**
     * Rule ids of opt-in checks this site has switched on. An opt-in check runs
     * only when listed as something other than `'off'`.
     *
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_map(strval(...), array_keys(array_filter(
            $this->rules,
            static fn (mixed $severity): bool => is_string($severity) && $severity !== 'off',
        )));
    }

    /**
     * Drop silenced issues and apply severity overrides.
     *
     * @param  list<Issue>  $issues
     * @return list<Issue>
     */
    public function apply(array $issues): array
    {
        $result = [];

        foreach ($issues as $issue) {
            $override = $this->rules[$issue->check] ?? null;

            if ($override === 'off') {
                continue;
            }

            $severity = match ($override) {
                'error' => Severity::Error,
                'warning', 'warn' => Severity::Warning,
                default => $issue->severity,
            };

            $result[] = $issue->severity === $severity ? $issue : $issue->withSeverity($severity);
        }

        return $result;
    }
}
