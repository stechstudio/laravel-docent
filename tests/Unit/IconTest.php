<?php

use STS\Docent\Support\Icon;

it('renders a bundled heroicon by name', function () {
    $svg = Icon::svg('book-open');

    expect($svg)->not->toBeNull()
        ->and($svg)->toContain('<svg')
        // Normalized to match the legacy output.
        ->and($svg)->toContain('width="24"')
        ->and($svg)->toContain('height="24"')
        ->and($svg)->toContain('aria-hidden="true"')
        // Heroicon stroke attributes are preserved.
        ->and($svg)->toContain('stroke="currentColor"')
        // The editor-only data-slot attribute is stripped.
        ->and($svg)->not->toContain('data-slot');
});

it('still renders a legacy icon name', function () {
    // `rocket` has no heroicon file, so it falls back to the legacy set.
    expect(Icon::svg('rocket'))->toContain('<svg');
});

it('returns null for an unknown icon name', function () {
    expect(Icon::svg('definitely-not-an-icon'))->toBeNull();
});

it('returns null (never touches disk) for a path-traversal name', function () {
    expect(Icon::svg('../foo'))->toBeNull()
        ->and(Icon::svg('../../etc/passwd'))->toBeNull()
        ->and(Icon::svg('foo/bar'))->toBeNull();
});

it('reports has() across both sources', function () {
    expect(Icon::has('book-open'))->toBeTrue()   // heroicon
        ->and(Icon::has('rocket'))->toBeTrue()    // legacy
        ->and(Icon::has('nope-nope'))->toBeFalse();
});

it('lists a sorted, merged, de-duplicated name set', function () {
    $names = Icon::names();

    $sorted = $names;
    sort($sorted);

    expect($names)->toBe($sorted)
        ->and($names)->toBe(array_values(array_unique($names)))
        ->and($names)->toContain('book-open')
        ->and($names)->toContain('rocket')
        // A comprehensive set, not just the ~17 legacy icons.
        ->and(count($names))->toBeGreaterThan(200);
});
