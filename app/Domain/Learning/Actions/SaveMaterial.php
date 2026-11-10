<?php

namespace App\Domain\Learning\Actions;

use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Enums\VideoProvider;
use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Support\VideoEmbed;
use App\Domain\Shared\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan materi beserta isinya sesuai jenis. File disimpan di disk privat dengan
 * nama acak; HTML artikel disanitasi; tautan video disimpan sebagai URL kanonik.
 */
class SaveMaterial
{
    public const DISK = 'local';

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * @param  array{title: string, type: MaterialType, is_required: bool, body_html?: string|null, external_url?: string|null}  $data
     */
    public function __invoke(LearningModule $module, ?LearningMaterial $material, array $data, ?UploadedFile $file): LearningMaterial
    {
        $type = $data['type'];
        $isNew = $material === null;

        if (! $isNew && $material->type !== $type) {
            throw ValidationException::withMessages(['type' => __('Jenis materi tidak dapat diubah. Buat materi baru.')]);
        }

        $attributes = [
            'title' => $data['title'],
            'type' => $type,
            'is_required' => $data['is_required'],
            'body_html' => null,
            'external_url' => null,
            'video_provider' => null,
        ];

        match ($type) {
            MaterialType::Article => $attributes['body_html'] = $this->articleBody($data['body_html'] ?? null),
            MaterialType::Link => $attributes['external_url'] = $data['external_url'] ?? null,
            MaterialType::VideoEmbed => $attributes = [...$attributes, ...$this->videoEmbed($data['external_url'] ?? null)],
            default => null,
        };

        $oldFile = null;

        if ($type->usesFile()) {
            if ($file === null && ($isNew || $material->file_path === null)) {
                throw ValidationException::withMessages(['file' => __('File materi wajib diunggah.')]);
            }

            if ($file !== null) {
                $oldFile = $material?->file_path;
                $attributes = [...$attributes, ...$this->storeFile($module, $file)];
            }
        }

        $saved = DB::transaction(function () use ($module, $material, $attributes) {
            $material ??= new LearningMaterial([
                'learning_module_id' => $module->id,
                'position' => (int) $module->materials()->max('position') + 1,
            ]);
            $material->fill($attributes)->save();

            return $material;
        });

        if ($oldFile !== null) {
            Storage::disk(self::DISK)->delete($oldFile);
        }

        return $saved;
    }

    private function articleBody(?string $html): string
    {
        $clean = $this->sanitizer->clean($html);

        if (! $this->sanitizer->hasVisibleText($clean)) {
            throw ValidationException::withMessages(['body_html' => __('Isi artikel wajib diisi.')]);
        }

        return $clean;
    }

    /**
     * @return array{external_url: string, video_provider: VideoProvider}
     */
    private function videoEmbed(?string $url): array
    {
        $embed = VideoEmbed::parse($url);

        if ($embed === null) {
            throw ValidationException::withMessages(['external_url' => __('Gunakan tautan video YouTube atau Vimeo yang valid.')]);
        }

        return ['external_url' => (string) $url, 'video_provider' => $embed->provider];
    }

    /**
     * @return array{file_path: string, file_original_name: string, file_mime: string, file_size: int}
     */
    private function storeFile(LearningModule $module, UploadedFile $file): array
    {
        $extension = $file->guessExtension() ?? $file->extension();
        $path = $file->storeAs(
            'materials/'.$module->program->public_id,
            Str::ulid()->toString().'.'.$extension,
            self::DISK,
        );

        return [
            'file_path' => (string) $path,
            'file_original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'file_mime' => (string) $file->getMimeType(),
            'file_size' => (int) $file->getSize(),
        ];
    }
}
