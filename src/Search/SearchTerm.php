<?php

declare(strict_types=1);

namespace STS\Docent\Search;

final class SearchTerm
{
    public function __construct(
        public readonly string $value,
        public readonly string $stem,
        public readonly bool $prefixEligible,
    ) {}
}
