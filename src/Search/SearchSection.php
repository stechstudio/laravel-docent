<?php

declare(strict_types=1);

namespace STS\Docent\Search;

final class SearchSection
{
    /**
     * @param  list<string>  $headingTokens
     * @param  list<string>  $bodyTokens
     */
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $slug,
        public readonly string $body,
        public readonly array $headingTokens,
        public readonly array $bodyTokens,
        public readonly int $order,
    ) {}
}
