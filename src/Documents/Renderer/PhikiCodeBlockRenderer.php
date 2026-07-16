<?php

declare(strict_types=1);

namespace STS\Docent\Documents\Renderer;

use Phiki\Phiki;
use Phiki\Theme\Theme;
use STS\Docent\Documents\Ast\CodeBlock;
use STS\Docent\Support\DocentCache;

/**
 * Server-side syntax highlighting via Phiki, emitting a single dual-theme
 * markup (github-light inline, github-dark behind CSS variables) so the same
 * HTML renders correctly in both colour schemes without a client round-trip.
 *
 * The highlighted `<pre>` is cached by content hash — TextMate tokenising is
 * pure PHP but not free, and a page must never pay that cost twice.
 */
final class PhikiCodeBlockRenderer implements CodeBlockRenderer
{
    private const THEMES = ['light' => Theme::GithubLight, 'dark' => Theme::GithubDark];

    private readonly Phiki $phiki;

    public function __construct(private readonly DocentCache $cache)
    {
        $this->phiki = new Phiki;
    }

    public function render(CodeBlock $node): string
    {
        $language = $this->language($node->language);
        $label = $node->filename() ?? $this->title($node->info) ?? ($language ?? 'text');

        $pre = $this->cache->remember(
            'code:'.sha1(($language ?? '').':'.$node->code),
            fn (): string => $this->highlight($node->code, $language),
        );

        return '<div class="docent-code" data-language="'.e($language ?? 'text').'">'
            .'<div class="docent-code-header">'
            .'<span class="docent-code-label">'.e($label).'</span>'
            .$this->copyButton()
            .'</div>'
            .$pre
            .'</div>';
    }

    private function highlight(string $code, ?string $language): string
    {
        $code = rtrim($code, "\n");

        if ($language === null) {
            return '<pre class="phiki docent-code-plain"><code>'.e($code).'</code></pre>';
        }

        return (string) $this->phiki->codeToHtml($code, $language, self::THEMES);
    }

    /**
     * A known, lowercased grammar name (or alias), or null for plain rendering.
     */
    private function language(?string $language): ?string
    {
        if ($language === null || $language === '') {
            return null;
        }

        $language = strtolower($language);

        return $this->phiki->environment->grammars->has($language) ? $language : null;
    }

    private function title(?string $info): ?string
    {
        if ($info !== null && preg_match('/title=(?:"([^"]*)"|(\S+))/', $info, $m) === 1) {
            return $m[1] !== '' ? $m[1] : ($m[2] ?? null);
        }

        return null;
    }

    private function copyButton(): string
    {
        return '<button type="button" class="docent-code-copy" data-docent-copy aria-label="Copy code">'
            .'<svg class="docent-copy-idle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
            .'<svg class="docent-copy-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>'
            .'</button>';
    }
}
