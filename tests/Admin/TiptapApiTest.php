<?php

declare(strict_types=1);

use STS\Docent\Documents\Ast\AuthorizationBlock;
use STS\Docent\Documents\Ast\Callout;
use STS\Docent\Documents\Ast\DynamicValue;
use STS\Docent\Documents\Parser\MarkdownDocumentParser;

require_once __DIR__.'/../Unit/Helpers.php';

/**
 * A representative Tiptap page: a gated block, an inline value chip, and a
 * callout — enough to prove gating, resolution, and directive rendering survive
 * the JSON → AST → reader path.
 */
function tiptapDemoDoc(): array
{
    return ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Tiptap Demo']]],
        ['type' => 'docsGate', 'attrs' => ['mode' => 'can', 'ability' => 'reports.view', 'arguments' => []], 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Secret admin content.']]],
        ]],
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Your plan is '],
            ['type' => 'docsValue', 'attrs' => ['key' => 'account.plan', 'arguments' => []]],
            ['type' => 'text', 'text' => '.'],
        ]],
        ['type' => 'docsCallout', 'attrs' => ['type' => 'note', 'title' => null], 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'A helpful note.']]],
        ]],
    ]];
}

it('saves a tiptap draft, publishes it, and the reader renders it', function () {
    $this->actingAs($this->adminUser())
        ->postJson('/docs/admin/api/pages', [
            'slug' => 'tiptap-demo',
            'title' => 'Tiptap Demo',
            'content_tiptap' => tiptapDemoDoc(),
        ])->assertCreated()
        ->assertJsonPath('format', 'tiptap')
        ->assertJsonPath('published', false);

    $this->actingAs($this->adminUser())->postJson('/docs/admin/api/pages/tiptap-demo/publish')->assertOk();

    // Admin passes reports.view: sees the gated content, the resolved value, and the callout.
    $this->actingAs($this->adminUser())->get('/docs/tiptap-demo')
        ->assertOk()
        ->assertSee('Secret admin content.')
        ->assertSee('Your plan is')
        ->assertSee('Team Plan')
        ->assertSee('A helpful note.');
});

it('gates tiptap content per viewer at read time', function () {
    $this->actingAs($this->adminUser())->postJson('/docs/admin/api/pages', [
        'slug' => 'tiptap-gated',
        'title' => 'Tiptap Gated',
        'content_tiptap' => tiptapDemoDoc(),
    ])->assertCreated();
    $this->actingAs($this->adminUser())->postJson('/docs/admin/api/pages/tiptap-gated/publish')->assertOk();

    // A member fails reports.view: the page renders, but the gated block is dropped.
    $this->actingAs($this->memberUser())->get('/docs/tiptap-gated')
        ->assertOk()
        ->assertDontSee('Secret admin content.')
        ->assertSee('A helpful note.');
});

it('returns content_tiptap for a tiptap page', function () {
    $this->actingAs($this->adminUser())->postJson('/docs/admin/api/pages', [
        'slug' => 'tiptap-detail',
        'title' => 'Tiptap Detail',
        'content_tiptap' => tiptapDemoDoc(),
    ])->assertCreated();

    $this->actingAs($this->adminUser())->getJson('/docs/admin/api/pages/tiptap-detail')
        ->assertOk()
        ->assertJsonPath('format', 'tiptap')
        ->assertJsonPath('content_tiptap.type', 'doc')
        ->assertJsonPath('content_tiptap.content.0.type', 'heading');
});

it('returns content_tiptap for a markdown file page (converted on the fly)', function () {
    $response = $this->actingAs($this->adminUser())->getJson('/docs/admin/api/pages/guides/setup')
        ->assertOk()
        ->assertJsonPath('format', 'markdown')
        ->assertJsonPath('store', 'filesystem')
        ->assertJsonPath('content_tiptap.type', 'doc');

    // The markdown body was really converted to editor nodes.
    expect($response->json('content_tiptap.content'))->toBeArray()->not->toBeEmpty();
});

it('rejects invalid tiptap with a 422 naming the bad node', function () {
    $this->actingAs($this->adminUser())->postJson('/docs/admin/api/pages', [
        'slug' => 'bad-tiptap',
        'title' => 'Bad',
        'content_tiptap' => ['type' => 'doc', 'content' => [['type' => 'bogusNode']]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('content_tiptap');
});

it('exports any page to markdown the markdown parser accepts', function () {
    $this->actingAs($this->adminUser())->postJson('/docs/admin/api/pages', [
        'slug' => 'tiptap-export',
        'title' => 'Tiptap Export',
        'content_tiptap' => tiptapDemoDoc(),
    ])->assertCreated();

    $markdown = $this->actingAs($this->adminUser())
        ->getJson('/docs/admin/api/pages/tiptap-export/markdown')
        ->assertOk()
        ->json('markdown');

    expect($markdown)->toContain('Tiptap Export')
        ->toContain(':::can ability="reports.view"')
        ->toContain('{{ value:account.plan }}')
        ->toContain(':::note');

    // The export re-parses into an equivalent AST.
    $document = (new MarkdownDocumentParser)->parse($markdown);

    expect(docFind($document, AuthorizationBlock::class))->not->toBeNull()
        ->and(docFind($document, DynamicValue::class)->key)->toBe('account.plan')
        ->and(docFind($document, Callout::class))->not->toBeNull();
});

it('exports a markdown file page to markdown too', function () {
    $markdown = $this->actingAs($this->adminUser())
        ->getJson('/docs/admin/api/pages/guides/setup/markdown')
        ->assertOk()
        ->json('markdown');

    expect($markdown)->toContain('title: Setup');
    expect(fn () => (new MarkdownDocumentParser)->parse($markdown))->not->toThrow(Throwable::class);
});

it('previews a tiptap draft, rendering it and reporting issues', function () {
    $draft = tiptapDemoDoc();
    // Introduce an unknown value to prove reference checks run over tiptap drafts.
    $draft['content'][] = ['type' => 'paragraph', 'content' => [['type' => 'docsValue', 'attrs' => ['key' => 'nope', 'arguments' => []]]]];

    $response = $this->actingAs($this->adminUser())
        ->postJson('/docs/admin/api/preview', ['content_tiptap' => $draft])
        ->assertOk();

    expect($response->json('html'))->toContain('Secret admin content')
        ->and($response->json('html'))->toContain('Team Plan')
        ->and(array_column($response->json('issues'), 'check'))->toContain('unknown-value');
});

it('rejects an invalid tiptap preview with a 422', function () {
    $this->actingAs($this->adminUser())
        ->postJson('/docs/admin/api/preview', ['content_tiptap' => ['type' => 'doc', 'content' => [['type' => 'nope']]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('content_tiptap');
});
