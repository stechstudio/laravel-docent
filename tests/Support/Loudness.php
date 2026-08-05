<?php

declare(strict_types=1);

namespace STS\Docent\Tests\Support;

/**
 * A pure (non-backed) enum, so its cases carry no value that could serve as an
 * ability name. Declaring it as an ability surface must fail loudly.
 */
enum Loudness
{
    case Quiet;
    case Loud;
}
