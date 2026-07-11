<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

/**
 * A single problem found by a {@see Check}: what went wrong, how serious it is,
 * and where (page slug + source line, when known). The `check` slug identifies
 * the check that produced it (kebab, e.g. `broken-link`).
 */
final class Issue
{
    public function __construct(
        public readonly Severity $severity,
        public readonly string $check,
        public readonly string $slug,
        public readonly string $message,
        public readonly ?int $line = null,
    ) {}

    public static function error(string $check, string $slug, string $message, ?int $line = null): self
    {
        return new self(Severity::Error, $check, $slug, $message, $line);
    }

    public static function warning(string $check, string $slug, string $message, ?int $line = null): self
    {
        return new self(Severity::Warning, $check, $slug, $message, $line);
    }

    public function isError(): bool
    {
        return $this->severity === Severity::Error;
    }
}
