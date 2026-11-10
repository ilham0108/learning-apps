<?php

namespace Database\Factories;

use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Enums\VideoProvider;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningMaterial>
 */
class LearningMaterialFactory extends Factory
{
    protected $model = LearningMaterial::class;

    /**
     * Artikel adalah jenis default karena tidak membutuhkan file.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'learning_module_id' => LearningModule::factory(),
            'title' => fake()->sentence(4),
            'type' => MaterialType::Article,
            'is_required' => true,
            'position' => fake()->numberBetween(1, 20),
            'body_html' => '<p>'.fake()->paragraph().'</p><p>'.fake()->paragraph().'</p>',
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    public function link(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaterialType::Link,
            'body_html' => null,
            'external_url' => 'https://example.com/'.fake()->slug(),
        ]);
    }

    public function videoEmbed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaterialType::VideoEmbed,
            'body_html' => null,
            'external_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'video_provider' => VideoProvider::Youtube,
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MaterialType::Pdf,
            'body_html' => null,
            'file_path' => 'materials/'.fake()->uuid().'.pdf',
            'file_original_name' => fake()->slug().'.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(50_000, 5_000_000),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
