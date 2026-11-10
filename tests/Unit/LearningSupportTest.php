<?php

namespace Tests\Unit;

use App\Domain\Learning\Enums\LearningProgressStatus;
use App\Domain\Learning\Enums\VideoProvider;
use App\Domain\Learning\Queries\ProgressSummary;
use App\Domain\Learning\Support\VideoEmbed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LearningSupportTest extends TestCase
{
    /**
     * @return array<string, array{string, VideoProvider, string}>
     */
    public static function videoUrls(): array
    {
        return [
            'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', VideoProvider::Youtube, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtube watch with params' => ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10', VideoProvider::Youtube, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', VideoProvider::Youtube, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'youtube shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', VideoProvider::Youtube, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'vimeo' => ['https://vimeo.com/76979871', VideoProvider::Vimeo, 'https://player.vimeo.com/video/76979871'],
            'vimeo player' => ['https://player.vimeo.com/video/76979871', VideoProvider::Vimeo, 'https://player.vimeo.com/video/76979871'],
        ];
    }

    #[DataProvider('videoUrls')]
    public function test_video_urls_are_recognised(string $url, VideoProvider $provider, string $embed)
    {
        $video = VideoEmbed::parse($url);

        $this->assertNotNull($video);
        $this->assertSame($provider, $video->provider);
        $this->assertSame($embed, $video->embedUrl());
    }

    public function test_other_urls_are_rejected()
    {
        foreach ([
            'https://evil.test/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com.evil.test/watch?v=dQw4w9WgXcQ',
            'javascript:alert(1)',
            'https://vimeo.com/abc',
            '',
            null,
        ] as $url) {
            $this->assertNull(VideoEmbed::parse($url), (string) $url);
        }
    }

    public function test_progress_summary_status_and_percent()
    {
        $this->assertSame(LearningProgressStatus::NotStarted, (new ProgressSummary(3, 0, false))->status());
        $this->assertSame(0, (new ProgressSummary(3, 0, false))->percent());

        $this->assertSame(LearningProgressStatus::InProgress, (new ProgressSummary(3, 0, true))->status());
        $this->assertSame(66, (new ProgressSummary(3, 2, true))->percent());

        $this->assertSame(LearningProgressStatus::Completed, (new ProgressSummary(3, 3, true))->status());
        $this->assertSame(100, (new ProgressSummary(3, 3, true))->percent());

        // Program tanpa materi wajib selesai setelah kandidat mulai membuka materi.
        $this->assertSame(LearningProgressStatus::NotStarted, (new ProgressSummary(0, 0, false))->status());
        $this->assertSame(LearningProgressStatus::Completed, (new ProgressSummary(0, 0, true))->status());
    }
}
