<?php

namespace App\Domain\Learning\Actions;

use App\Domain\Learning\Models\LearningMaterial;
use App\Domain\Shared\Audit\ActivityLogger;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Materi yang sudah memiliki progres kandidat diarsipkan agar riwayat tetap utuh;
 * materi yang belum pernah dibuka dihapus beserta filenya.
 */
class RemoveMaterial
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @return 'archived'|'deleted'
     */
    public function __invoke(LearningMaterial $material, User $actor): string
    {
        if ($material->progress()->exists()) {
            $material->forceFill(['archived_at' => now()])->save();
            $this->logger->log('material.archived', $material, actor: $actor);

            return 'archived';
        }

        $path = $material->file_path;
        $this->logger->log('material.deleted', $material->module->program, ['title' => $material->title], actor: $actor);
        $material->delete();

        if ($path !== null) {
            Storage::disk(SaveMaterial::DISK)->delete($path);
        }

        return 'deleted';
    }
}
