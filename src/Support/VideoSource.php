<?php

declare(strict_types=1);

namespace STS\Docent\Support;

/** Classifies video URLs and derives privacy-conscious provider embed URLs. */
final class VideoSource
{
    private const FILE_TYPES = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
    ];

    private function __construct(
        public readonly string $kind,
        public readonly string $url,
        public readonly ?string $embedUrl = null,
        public readonly ?string $mimeType = null,
    ) {}

    public static function parse(string $url): ?self
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (isset(self::FILE_TYPES[$extension])) {
            return new self('file', $url, mimeType: self::FILE_TYPES[$extension]);
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            $id = self::youtubeId($url, $host, $path);

            return $id === null ? null : new self(
                'youtube',
                $url,
                'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1',
            );
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            $id = self::pathId($path, 'video');

            return $id === null ? null : new self(
                'vimeo',
                $url,
                'https://player.vimeo.com/video/'.$id.'?autoplay=1',
            );
        }

        if ($host === 'loom.com') {
            $id = self::pathId($path, 'share');

            return $id === null ? null : new self(
                'loom',
                $url,
                'https://www.loom.com/embed/'.$id.'?autoplay=1',
            );
        }

        return null;
    }

    public function isFile(): bool
    {
        return $this->kind === 'file';
    }

    private static function youtubeId(string $url, string $host, string $path): ?string
    {
        if ($host === 'youtu.be') {
            return self::safeId(explode('/', trim($path, '/'))[0]);
        }

        parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
        if (is_string($query['v'] ?? null)) {
            return self::safeId($query['v']);
        }

        return self::pathId($path, 'embed') ?? self::pathId($path, 'shorts');
    }

    private static function pathId(string $path, string $prefix): ?string
    {
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        $prefixIndex = array_search($prefix, $parts, true);

        if ($prefixIndex !== false) {
            return self::safeId($parts[$prefixIndex + 1] ?? '');
        }

        return $prefix === 'video' ? self::safeId($parts[0] ?? '') : null;
    }

    private static function safeId(string $id): ?string
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1 ? $id : null;
    }
}
