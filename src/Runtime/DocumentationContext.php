<?php

declare(strict_types=1);

namespace STS\Docent\Runtime;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * The per-request rendering context: who is viewing, with what parameters,
 * and how to answer authorization questions.
 *
 * The gate check is injected as a closure so the runtime stays framework-light
 * and unit-testable without booting Laravel. The Laravel wiring (Gate::forUser)
 * is provided in a later milestone.
 */
final class DocumentationContext
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  ?Closure(string $ability, array<int, mixed> $arguments, ?Authenticatable $user): bool  $gate
     */
    public function __construct(
        public readonly ?Authenticatable $user = null,
        public readonly ?Request $request = null,
        public readonly array $parameters = [],
        public readonly ?string $audience = null,
        private readonly ?Closure $gate = null,
    ) {}

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function can(string $ability, array $arguments = []): bool
    {
        if ($this->gate === null) {
            return false;
        }

        return (bool) ($this->gate)($ability, $arguments, $this->user);
    }

    public function parameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }
}
