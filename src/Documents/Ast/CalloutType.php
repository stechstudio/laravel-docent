<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

enum CalloutType: string
{
    case Note = 'note';
    case Tip = 'tip';
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';

    public static function tryFromName(string $name): ?self
    {
        return self::tryFrom(strtolower($name));
    }
}
