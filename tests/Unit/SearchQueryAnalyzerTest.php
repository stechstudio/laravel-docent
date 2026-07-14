<?php

declare(strict_types=1);

use STS\Docent\Search\SearchQueryAnalyzer;

it('separates filler words from meaningful search terms', function () {
    $query = (new SearchQueryAnalyzer)->analyze('How do I insert a video?');

    expect($query->allTerms)->toBe(['how', 'do', 'i', 'insert', 'a', 'video'])
        ->and(array_map(fn ($term) => $term->value, $query->terms))->toBe(['insert', 'video'])
        ->and($query->terms[0]->prefixEligible)->toBeFalse()
        ->and($query->terms[1]->prefixEligible)->toBeTrue();
});

it('falls back to original terms when a query contains only stop words', function () {
    $query = (new SearchQueryAnalyzer)->analyze('how to');

    expect(array_map(fn ($term) => $term->value, $query->terms))->toBe(['how', 'to']);
});

it('allows hosts to replace the default stop-word list', function () {
    $query = (new SearchQueryAnalyzer(['video']))->analyze('how video');

    expect(array_map(fn ($term) => $term->value, $query->terms))->toBe(['how']);
});
