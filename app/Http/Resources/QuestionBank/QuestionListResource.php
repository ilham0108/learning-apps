<?php

namespace App\Http\Resources\QuestionBank;

use App\Domain\QuestionBank\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Baris daftar bank soal dan pemilih soal ujian (tanpa kunci jawaban).
 *
 * @mixin Question
 */
class QuestionListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'excerpt' => Str::limit(trim(html_entity_decode(strip_tags($this->body_html))), 160),
            'category' => $this->category->name,
            'topic' => $this->topic,
            'type' => $this->type->value,
            'difficulty' => $this->difficulty->value,
            'default_points' => $this->default_points,
            'status' => $this->status->value,
            'version' => $this->version,
            'children_count' => $this->whenCounted('children'),
            'usage_count' => $this->whenCounted('examQuestions'),
        ];
    }
}
