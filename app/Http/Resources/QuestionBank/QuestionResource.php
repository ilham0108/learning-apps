<?php

namespace App\Http\Resources\QuestionBank;

use App\Domain\QuestionBank\Models\Question;
use App\Domain\QuestionBank\Models\QuestionAcceptedAnswer;
use App\Domain\QuestionBank\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Soal lengkap untuk HR, termasuk kunci jawaban. Tidak pernah dipakai di halaman
 * kandidat; kandidat hanya menerima snapshot tanpa kunci (Tahap 7).
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->public_id, 'name' => $this->category->name]),
            'topic' => $this->topic,
            'version' => $this->version,
            'status' => $this->status->value,
            'is_superseded' => $this->superseded_at !== null,
            ...self::content($this->resource),
            'children' => $this->whenLoaded('children', fn () => $this->children->map(fn (Question $child) => self::content($child))->values()->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function content(Question $question): array
    {
        return [
            'type' => $question->type->value,
            'difficulty' => $question->difficulty->value,
            'body_html' => $question->body_html,
            'explanation_html' => $question->explanation_html,
            'rubric' => $question->rubric,
            'default_points' => $question->default_points,
            'options' => $question->options->map(fn (QuestionOption $option) => [
                'body' => $option->body_html,
                'is_correct' => $option->is_correct,
            ])->values()->all(),
            'accepted_answers' => $question->acceptedAnswers->map(fn (QuestionAcceptedAnswer $answer) => $answer->answer)->values()->all(),
        ];
    }
}
