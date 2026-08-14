<?php

use STS\Docent\Sharing\ShareToken;

const KEY = 'test-key';

const TODAY = 20678;

it('verifies a token it minted', function () {
    $token = ShareToken::mint('/docs/billing', TODAY + 30, KEY);

    expect(ShareToken::expiryDay('/docs/billing', $token, KEY, TODAY))->toBe(TODAY + 30);
});

it('stays short enough to paste into a support reply', function () {
    expect(ShareToken::mint('/docs/billing', TODAY + 30, KEY))->toHaveLength(14);
});

it('rejects a token minted for a different path', function () {
    $token = ShareToken::mint('/docs/billing', TODAY + 30, KEY);

    expect(ShareToken::expiryDay('/docs/internals', $token, KEY, TODAY))->toBeNull();
});

it('rejects a token whose day has passed', function () {
    $token = ShareToken::mint('/docs/billing', TODAY - 1, KEY);

    expect(ShareToken::expiryDay('/docs/billing', $token, KEY, TODAY))->toBeNull();
});

it('accepts a token expiring today', function () {
    $token = ShareToken::mint('/docs/billing', TODAY, KEY);

    expect(ShareToken::expiryDay('/docs/billing', $token, KEY, TODAY))->toBe(TODAY);
});

it('rejects a tampered mac', function () {
    $token = ShareToken::mint('/docs/billing', TODAY + 30, KEY);
    $tampered = substr($token, 0, -1).(str_ends_with($token, 'A') ? 'B' : 'A');

    expect(ShareToken::expiryDay('/docs/billing', $tampered, KEY, TODAY))->toBeNull();
});

it('rejects an extended expiry that keeps the original mac', function () {
    $token = ShareToken::mint('/docs/billing', TODAY + 1, KEY);
    $mac = substr($token, -11);

    $extended = base_convert((string) (TODAY + 900), 10, 36).$mac;

    expect(ShareToken::expiryDay('/docs/billing', $extended, KEY, TODAY))->toBeNull();
});

it('rejects a token minted under a different key', function () {
    $token = ShareToken::mint('/docs/billing', TODAY + 30, KEY);

    expect(ShareToken::expiryDay('/docs/billing', $token, 'rotated-key', TODAY))->toBeNull();
});

it('rejects a token with no expiry segment', function () {
    $mac = substr(ShareToken::mint('/docs/billing', TODAY + 30, KEY), -11);

    expect(ShareToken::expiryDay('/docs/billing', $mac, KEY, TODAY))->toBeNull();
});

it('rejects a malformed expiry segment', function () {
    $mac = substr(ShareToken::mint('/docs/billing', TODAY + 30, KEY), -11);

    expect(ShareToken::expiryDay('/docs/billing', '!!'.$mac, KEY, TODAY))->toBeNull();
});

it('rejects an empty token', function () {
    expect(ShareToken::expiryDay('/docs/billing', '', KEY, TODAY))->toBeNull();
});

it('survives a day that needs a wider base36 segment', function () {
    $token = ShareToken::mint('/docs/billing', 46656, KEY);

    expect($token)->toHaveLength(15)
        ->and(ShareToken::expiryDay('/docs/billing', $token, KEY, TODAY))->toBe(46656);
});

it('produces url-safe characters only', function () {
    $tokens = array_map(
        fn (int $i): string => ShareToken::mint('/docs/page-'.$i, TODAY + $i, KEY),
        range(1, 200),
    );

    expect(array_filter($tokens, fn (string $t): bool => preg_match('/^[A-Za-z0-9_-]+$/', $t) !== 1))->toBeEmpty();
});

it('rejects an expiry segment too wide to convert', function () {
    // base_convert() overflows to INF past roughly 200 base36 digits and then
    // throws, so an unbounded segment turns a crafted query string into a 500.
    $mac = substr(ShareToken::mint('/docs/billing', TODAY + 30, KEY), -11);

    expect(ShareToken::expiryDay('/docs/billing', str_repeat('9', 400).$mac, KEY, TODAY))->toBeNull()
        ->and(ShareToken::expiryDay('/docs/billing', str_repeat('z', 200).$mac, KEY, TODAY))->toBeNull();
});

it('rejects an expiry segment one character past the ceiling', function () {
    $mac = substr(ShareToken::mint('/docs/billing', TODAY + 30, KEY), -11);

    expect(ShareToken::expiryDay('/docs/billing', 'zzzzzzz'.$mac, KEY, TODAY))->toBeNull();
});
