<?php

declare(strict_types=1);

namespace STS\Docent\Ai;

use LogicException;

final class PrismGuard
{
    public function __construct(
        private readonly string $facadeClass = 'Prism\\Prism\\Facades\\Prism',
    ) {}

    public function ensureInstalled(): void
    {
        if (! class_exists($this->facadeClass)) {
            throw new LogicException(
                'Docent AI is enabled, but Prism is not installed. Install prism-php/prism or disable docent.ai.enabled.',
            );
        }
    }
}
