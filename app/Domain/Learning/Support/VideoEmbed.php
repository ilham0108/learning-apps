<?php

namespace App\Domain\Learning\Support;

use App\Domain\Learning\Enums\VideoProvider;

/**
 * Mengenali tautan YouTube/Vimeo dan membentuk URL embed resmi.
 * URL embed dibentuk dari ID video, tidak pernah dari input mentah.
 */
final class VideoEmbed
{
    private function __construct(
        public readonly VideoProvider $provider,
        public readonly string $videoId,
    ) {}

    public static function parse(?string $url): ?self
    {
        if ($url === null || $url === '') {
            return null;
        }

        $patterns = [
            [VideoProvider::Youtube, '~^https?://(?:www\.|m\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})~'],
            [VideoProvider::Youtube, '~^https?://youtu\.be/([A-Za-z0-9_-]{11})~'],
            [VideoProvider::Youtube, '~^https?://(?:www\.)?youtube(?:-nocookie)?\.com/(?:embed|shorts)/([A-Za-z0-9_-]{11})~'],
            [VideoProvider::Vimeo, '~^https?://(?:www\.)?vimeo\.com/(\d{6,12})(?:[/?#].*)?$~'],
            [VideoProvider::Vimeo, '~^https?://player\.vimeo\.com/video/(\d{6,12})~'],
        ];

        foreach ($patterns as [$provider, $pattern]) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return new self($provider, $matches[1]);
            }
        }

        return null;
    }

    public function embedUrl(): string
    {
        return match ($this->provider) {
            VideoProvider::Youtube => "https://www.youtube-nocookie.com/embed/{$this->videoId}",
            VideoProvider::Vimeo => "https://player.vimeo.com/video/{$this->videoId}",
        };
    }
}
