<?php

declare(strict_types=1);

namespace STS\Docent\Validation;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'error',
            self::Warning => 'warning',
        };
    }
}
