<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Ast;

enum AppLinkKind: string
{
    case Link = 'link';
    case Route = 'route';
}
