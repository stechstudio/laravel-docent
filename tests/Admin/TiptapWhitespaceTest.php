<?php

/**
 * Laravel's global TrimStrings middleware trims every nested input string, but
 * whitespace inside rich-text nodes is meaningful: "Plan: " followed by an
 * inline value chip must keep its trailing space. The admin controllers read
 * the Tiptap payload from the raw request body to dodge the mutation.
 */
it('preserves meaningful whitespace in tiptap text nodes through save and export', function () {
    $doc = [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [
                ['type' => 'text', 'text' => 'Plan: '],
                ['type' => 'docsValue', 'attrs' => ['key' => 'account.plan', 'arguments' => []]],
            ],
        ]],
    ];

    $this->actingAs($this->adminUser())
        ->postJson('/docs/_admin/api/pages', [
            'slug' => 'whitespace-check',
            'title' => 'Whitespace Check',
            'content_tiptap' => $doc,
        ])
        ->assertCreated();

    $detail = $this->actingAs($this->adminUser())
        ->getJson('/docs/_admin/api/pages/whitespace-check')
        ->assertOk()
        ->json();

    expect($detail['content_tiptap']['content'][0]['content'][0]['text'])->toBe('Plan: ');

    $markdown = $this->actingAs($this->adminUser())
        ->getJson('/docs/_admin/api/pages/whitespace-check/markdown')
        ->assertOk()
        ->json('markdown');

    expect($markdown)->toContain('Plan: {{ value:account.plan }}');
});
