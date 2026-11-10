<?php

namespace App\Http\Requests\Admin;

use App\Domain\Learning\Enums\MaterialType;
use App\Domain\Learning\Models\LearningMaterial;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi materi per jenis. File divalidasi berdasarkan tipe MIME sebenarnya
 * (dibaca dari isi file), ekstensi, dan ukuran.
 */
class MaterialRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->enum('type', MaterialType::class);
        /** @var LearningMaterial|null $material */
        $material = $this->route('material');

        $fileRequired = $type?->usesFile() && ($material === null || $material->file_path === null);

        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(MaterialType::class)],
            'is_required' => ['required', 'boolean'],
            'body_html' => [Rule::requiredIf($type === MaterialType::Article), 'nullable', 'string', 'max:200000'],
            'external_url' => [
                Rule::requiredIf(in_array($type, [MaterialType::Link, MaterialType::VideoEmbed], true)),
                'nullable', 'string', 'max:2048', 'url:http,https',
            ],
            'file' => [$fileRequired ? 'required' : 'nullable', ...($type?->fileRules() ?? ['prohibited'])],
        ];
    }

    /**
     * @return array{title: string, type: MaterialType, is_required: bool, body_html: string|null, external_url: string|null}
     */
    public function materialData(): array
    {
        return [
            'title' => $this->string('title')->trim()->toString(),
            'type' => $this->enum('type', MaterialType::class) ?? MaterialType::Article,
            'is_required' => $this->boolean('is_required'),
            'body_html' => $this->filled('body_html') ? $this->string('body_html')->toString() : null,
            'external_url' => $this->filled('external_url') ? $this->string('external_url')->trim()->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul materi',
            'type' => 'jenis materi',
            'body_html' => 'isi artikel',
            'external_url' => 'tautan',
            'file' => 'file',
        ];
    }
}
