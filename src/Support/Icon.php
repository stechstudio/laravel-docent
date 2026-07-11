<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/**
 * Inline SVG icons for cards, nav groups, and the admin picker. The primary
 * source is the bundled Heroicons 24px outline set under `resources/icons`
 * (~316 files, read on demand); a small legacy Feather-style set is kept as a
 * fallback so existing content referencing names like `rocket`/`chart` never
 * breaks. Every icon is stroked `currentColor` line art so it inherits the
 * surrounding text colour and stays crisp in light and dark. Unknown names
 * resolve to null — the card simply renders without an icon and `docent:check`
 * warns.
 */
final class Icon
{
    /**
     * Per-request cache of normalized Heroicon file contents, keyed by name
     * (null memoizes a miss so a bad name is only touched once).
     *
     * @var array<string, string|null>
     */
    private static array $fileCache = [];

    /**
     * Sorted list of bundled Heroicon names (filename minus `.svg`), scanned
     * once per request.
     *
     * @var list<string>|null
     */
    private static ?array $heroiconNames = null;

    /**
     * Path/shape markup for each icon, wrapped in a 24×24 stroked <svg> by
     * {@see svg()}.
     *
     * @var array<string, string>
     */
    private const ICONS = [
        'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'sparkles' => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.9 2.4L22 18l-2.1.6L19 21l-.9-2.4L16 18l2.1-.6z"/>',
        'terminal' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'bolt' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'life-buoy' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/><line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/><line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/><line x1="9.17" y1="14.83" x2="4.93" y2="19.07"/>',
        'key' => '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
    ];

    public static function svg(string $name): ?string
    {
        // Path-traversal guard: only ever touch the filesystem for a plain,
        // lowercase, dash-separated name (heroicons all match this).
        if (preg_match('/^[a-z0-9-]+$/', $name) === 1 && ($file = self::fileSvg($name)) !== null) {
            return $file;
        }

        $shapes = self::ICONS[$name] ?? null;

        if ($shapes === null) {
            return null;
        }

        return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" '
            .'stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            .$shapes.'</svg>';
    }

    public static function has(string $name): bool
    {
        return isset(self::ICONS[$name]) || in_array($name, self::heroiconNames(), true);
    }

    /**
     * Every icon name across both sources (heroicons + legacy), sorted and
     * de-duplicated, for the admin icon picker.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        $merged = array_unique([...self::heroiconNames(), ...array_keys(self::ICONS)]);
        sort($merged);

        return array_values($merged);
    }

    /**
     * The normalized markup for a bundled Heroicon, or null when there is no
     * such file. The root `<svg>` tag is rewritten so every icon matches the
     * legacy output: explicit `width`/`height` of 24 and `aria-hidden="true"`
     * (heroicons already carry `fill="none" stroke="currentColor"
     * stroke-width="1.5"`, which we keep).
     */
    private static function fileSvg(string $name): ?string
    {
        if (array_key_exists($name, self::$fileCache)) {
            return self::$fileCache[$name];
        }

        $path = self::iconDir().'/'.$name.'.svg';

        return self::$fileCache[$name] = is_file($path)
            ? self::normalize(trim((string) file_get_contents($path)))
            : null;
    }

    private static function normalize(string $svg): string
    {
        return (string) preg_replace_callback(
            '/^<svg\b([^>]*)>/',
            static function (array $matches): string {
                // Drop attributes we set canonically (plus the editor-only
                // `data-slot`), then re-add the canonical ones up front.
                $attrs = (string) preg_replace('/\s+(?:width|height|aria-hidden|data-slot)="[^"]*"/', '', $matches[1]);

                return '<svg width="24" height="24" aria-hidden="true"'.$attrs.'>';
            },
            $svg,
            1,
        );
    }

    /**
     * @return list<string>
     */
    private static function heroiconNames(): array
    {
        if (self::$heroiconNames !== null) {
            return self::$heroiconNames;
        }

        $names = [];

        foreach (glob(self::iconDir().'/*.svg') ?: [] as $path) {
            $names[] = basename($path, '.svg');
        }

        sort($names);

        return self::$heroiconNames = $names;
    }

    private static function iconDir(): string
    {
        return __DIR__.'/../../resources/icons';
    }
}
