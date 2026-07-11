<?php

/**
 * Preview renders an unsaved draft through the real pipeline with the admin's
 * own context, and runs the inline reference checks over it.
 */
$draft = <<<'MD'
# Draft

:::can ability="reports.view"
Secret admin content.
:::

The plan is {{ value:nope }}.

[Broken](does-not-exist)
MD;

it('renders gated content for an admin who passes the ability', function () use ($draft) {
    $response = $this->actingAs($this->adminUser())
        ->postJson('/docs/_admin/api/preview', ['content' => $draft])
        ->assertOk();

    expect($response->json('html'))->toContain('Secret admin content');
});

it('hides gated content for a viewer who fails the ability', function () use ($draft) {
    $response = $this->actingAs($this->memberUser())
        ->postJson('/docs/_admin/api/preview', ['content' => $draft]);

    // Members cannot reach the panel at all; this proves the gate, not the render.
    $response->assertForbidden();
});

it('reports reference issues for unknown values and broken links', function () use ($draft) {
    $issues = $this->actingAs($this->adminUser())
        ->postJson('/docs/_admin/api/preview', ['content' => $draft])
        ->assertOk()
        ->json('issues');

    $checks = array_column($issues, 'check');

    expect($checks)->toContain('unknown-value')
        ->and($checks)->toContain('broken-link');
});

it('returns a table of contents built for the viewer', function () {
    $content = "# Title\n\n## Section One\n\n## Section Two";

    $toc = $this->actingAs($this->adminUser())
        ->postJson('/docs/_admin/api/preview', ['content' => $content])
        ->assertOk()
        ->json('toc');

    expect($toc)->toHaveCount(2)
        ->and($toc[0]['title'])->toBe('Section One');
});
