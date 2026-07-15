<?php

declare(strict_types=1);

namespace STS\Docent\Documents;

enum HtmlPolicy: string
{
    case Trusted = 'trusted';
    case Sanitized = 'sanitized';
    case Denied = 'denied';
}
