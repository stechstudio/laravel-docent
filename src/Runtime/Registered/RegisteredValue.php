<?php

declare(strict_types=1);

namespace STS\Docent\Runtime\Registered;

use Closure;

final class RegisteredValue
{
    /**
     * @param  Closure|class-string  $resolver
     */
    public function __construct(
        public readonly string $name,
        public readonly Closure|string $resolver,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
    ) {}
}
