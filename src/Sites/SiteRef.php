<?php

declare(strict_types=1);

namespace STS\Docent\Sites;

/**
 * The current site's identity as seen by integration closures — enough to
 * branch on (`$context->site->key`) without exposing the service graph.
 */
final class SiteRef
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
    ) {}
}
