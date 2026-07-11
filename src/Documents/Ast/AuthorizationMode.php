<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

enum AuthorizationMode: string
{
    case Can = 'can';
    case Cannot = 'cannot';

    /**
     * Whether a gate check result should grant visibility under this mode.
     */
    public function grants(bool $allowed): bool
    {
        return match ($this) {
            self::Can => $allowed,
            self::Cannot => ! $allowed,
        };
    }
}
