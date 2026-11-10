<?php

namespace App\Http\Resources\Learning;

use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Learning\Models\LearningModule;
use App\Domain\Learning\Models\LearningProgram;
use App\Domain\Learning\Support\MaterialContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Program lengkap untuk penyusun konten (HR). Materi yang diarsipkan tetap
 * ditampilkan agar HR tahu riwayatnya, tetapi ditandai.
 *
 * @mixin LearningProgram
 */
class ProgramDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'status' => $this->status->value,
            'estimated_minutes' => $this->estimated_minutes,
            'target_position' => $this->targetPosition === null ? null : [
                'id' => $this->targetPosition->public_id,
                'name' => $this->targetPosition->name,
            ],
            'modules' => $this->whenLoaded('modules', fn () => $this->modules->map(fn (LearningModule $module) => [
                'id' => $module->public_id,
                'title' => $module->title,
                'description' => $module->description,
                'materials' => $module->materials->map(fn (LearningMaterial $material) => [
                    'id' => $material->public_id,
                    'title' => $material->title,
                    'type' => $material->type->value,
                    'is_required' => $material->is_required,
                    'is_archived' => $material->archived_at !== null,
                    'file_name' => $material->file_original_name,
                    'preview_url' => $material->type->usesFile() && $material->file_path !== null
                        ? MaterialContent::signedFileUrl($material)
                        : null,
                ])->values()->all(),
            ])->values()->all()),
        ];
    }
}
