<?php

use STS\Docent\Support\SafeUrl;

it('allows safe schemes and non-scheme destinations', function (string $destination) {
    expect(SafeUrl::filter($destination))->toBe($destination);
})->with([
    'http' => 'http://example.com',
    'https' => 'https://example.com',
    'mailto' => 'mailto:a@example.com',
    'tel' => 'tel:+15551234567',
    'relative' => 'getting-started/intro',
    'anchor' => '#section',
    'query' => '?tab=api',
    'protocol-relative' => '//cdn.example.com/asset',
]);

it('rejects unsafe schemes', function (string $destination) {
    expect(SafeUrl::filter($destination))->toBeNull();
})->with([
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html,<script>alert(1)</script>',
    'vbscript' => 'vbscript:msgbox(1)',
    'uppercase javascript' => 'JavaScript:alert(1)',
    'leading whitespace' => '  javascript:alert(1)',
]);
