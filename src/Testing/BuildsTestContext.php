<?php

declare(strict_types=1);

namespace STS\Docent\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use STS\Docent\Runtime\DocumentationContext;

/**
 * Builds a real, Gate-backed {@see DocumentationContext} for a given viewer, so
 * testing assertions exercise the same authorization path as HTTP requests.
 */
trait BuildsTestContext
{
    private function testContext(?Authenticatable $user, ?string $audience = null): DocumentationContext
    {
        return new DocumentationContext(
            user: $user,
            audience: $audience,
            gate: static fn (string $ability, array $arguments, ?Authenticatable $viewer): bool => $viewer !== null
                ? Gate::forUser($viewer)->allows($ability, $arguments)
                : Gate::allows($ability, $arguments),
        );
    }
}
