<?php

namespace App\Http\Resources\Candidates;

use App\Domain\Candidates\Models\CandidateDocument;
use App\Domain\Candidates\Models\CandidateEducation;
use App\Domain\Candidates\Models\CandidateExperience;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Domain\Candidates\Support\ProfileCompleteness;
use Illuminate\Support\Facades\URL;

/**
 * Data diri kandidat untuk halaman profil kandidat dan detail kandidat HR.
 * File dokumen hanya dapat dibuka lewat URL bertanda tangan yang berlaku 10 menit.
 */
class PersonalDataPresenter
{
    /**
     * @param  bool  $includeSensitive  Sertakan agama, status pernikahan, dan foto KTP
     *                                  (kandidat sendiri, atau staf dengan hak `candidates.view_sensitive`).
     * @return array<string, mixed>
     */
    public static function make(CandidateProfile $profile, bool $includeSensitive = true): array
    {
        $profile->loadMissing(['user', 'educations', 'experiences', 'documents']);

        return [
            'sensitive_hidden' => ! $includeSensitive,
            'personal' => [
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'phone' => $profile->phone,
                'city' => $profile->city,
                'birth_date' => $profile->birth_date?->toDateString(),
                'religion' => $includeSensitive ? $profile->religion?->value : null,
                'marital_status' => $includeSensitive ? $profile->marital_status?->value : null,
                'linkedin_url' => $profile->linkedin_url,
                'portfolio_url' => $profile->portfolio_url,
            ],
            'educations' => $profile->educations->map(fn (CandidateEducation $education) => [
                'id' => $education->public_id,
                'level' => $education->level->value,
                'institution' => $education->institution,
                'major' => $education->major,
                'start_year' => $education->start_year,
                'graduation_year' => $education->graduation_year,
            ])->values()->all(),
            'experiences' => $profile->experiences->map(fn (CandidateExperience $experience) => [
                'id' => $experience->public_id,
                'company' => $experience->company,
                'job_title' => $experience->job_title,
                'started_on' => $experience->started_on->format('Y-m'),
                'ended_on' => $experience->ended_on?->format('Y-m'),
                'description' => $experience->description,
            ])->values()->all(),
            'documents' => $profile->documents
                ->filter(fn (CandidateDocument $document) => $includeSensitive || ! $document->type->isSensitive())
                ->map(fn (CandidateDocument $document) => [
                    'id' => $document->public_id,
                    'type' => $document->type->value,
                    'label' => $document->label,
                    'name' => $document->file_original_name,
                    'mime' => $document->file_mime,
                    'size' => $document->file_size,
                    'uploaded_at' => $document->created_at?->toIso8601String(),
                    'url' => URL::temporarySignedRoute('candidate-documents.file', now()->addMinutes(10), $document),
                ])->values()->all(),
            'completeness' => ProfileCompleteness::check($profile),
        ];
    }
}
