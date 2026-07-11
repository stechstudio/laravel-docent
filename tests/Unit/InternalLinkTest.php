<?php

use STS\Docent\Support\InternalLink;

it('resolves destinations with file-path semantics', function (string $destination, string $baseDir, ?string $slug, string $suffix = '') {
    $target = InternalLink::resolve($destination, $baseDir, 'docs');

    if ($slug === null) {
        expect($target)->toBeNull();
    } else {
        expect($target)->toBe(['slug' => $slug, 'suffix' => $suffix]);
    }
})->with([
    'sibling' => ['installation', 'getting-started', 'getting-started/installation'],
    'parent-relative' => ['../billing/overview', 'getting-started', 'billing/overview'],
    'dot-slash' => ['./quickstart', 'getting-started', 'getting-started/quickstart'],
    'from root page' => ['getting-started/installation', '', 'getting-started/installation'],
    'above root clamps' => ['../../whatever', 'billing', 'whatever'],
    'with fragment' => ['installation#step-2', 'getting-started', 'getting-started/installation', '#step-2'],
    'docs-rooted' => ['/docs/billing/overview', 'getting-started', 'billing/overview'],
    'docs root itself' => ['/docs', 'getting-started', ''],
    'app path is external' => ['/billing/settings', 'getting-started', null],
    'absolute url is external' => ['https://example.com/docs/x', 'getting-started', null],
    'protocol-relative is external' => ['//cdn.example.com/x', 'getting-started', null],
    'mailto is external' => ['mailto:hi@example.com', 'getting-started', null],
    'pure anchor is external' => ['#section', 'getting-started', null],
]);
