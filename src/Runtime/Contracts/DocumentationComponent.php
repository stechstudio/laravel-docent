<?php

declare(strict_types=1);

namespace STS\Docent\Runtime\Contracts;

use Illuminate\Contracts\Support\Htmlable;
use STS\Docent\Runtime\DocumentationContext;

interface DocumentationComponent
{
    /**
     * Render the component to trusted HTML for the given context.
     *
     * @param  array<string, string>  $attributes
     */
    public function render(DocumentationContext $context, array $attributes): Htmlable|string;
}
