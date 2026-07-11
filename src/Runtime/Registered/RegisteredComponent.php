<?php

declare(strict_types=1);

namespace STS\Docent\Runtime\Registered;

use Closure;
use STS\Docent\Runtime\Contracts\DocumentationComponent;

final class RegisteredComponent
{
    /**
     * @param  Closure|class-string|DocumentationComponent  $resolver
     */
    public function __construct(
        public readonly string $name,
        public readonly Closure|string|DocumentationComponent $resolver,
        public readonly ?string $label = null,
        public readonly ?string $description = null,
    ) {}
}
