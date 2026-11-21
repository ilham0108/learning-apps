<?php

namespace App\Http\Controllers\Candidate;

use App\Domain\Candidates\Actions\RemoveCandidateDocument;
use App\Domain\Candidates\Actions\StoreCandidateDocument;
use App\Domain\Candidates\Enums\CandidateDocumentType;
use App\Domain\Candidates\Models\CandidateDocument;
use App\Domain\Candidates\Models\CandidateEducation;
use App\Domain\Candidates\Models\CandidateExperience;
use App\Domain\Candidates\Models\CandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\DocumentRequest;
use App\Http\Requests\Candidate\EducationRequest;
use App\Http\Requests\Candidate\ExperienceRequest;
use App\Http\Requests\Candidate\PersonalDataRequest;
use App\Http\Resources\Candidates\PersonalDataPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman data diri kandidat: identitas, pendidikan, pengalaman kerja, dan
 * dokumen. Semua data selalu diambil dari profil milik pengguna yang login,
 * sehingga kandidat tidak dapat mengubah data kandidat lain.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('candidate/profile/edit', PersonalDataPresenter::make($this->profile($request)));
    }

    public function updatePersonal(PersonalDataRequest $request): RedirectResponse
    {
        $profile = $this->profile($request);
        $data = $request->validated();

        DB::transaction(function () use ($profile, $data): void {
            $profile->user->update(['name' => $data['name']]);
            $profile->update([
                'phone' => $data['phone'],
                'city' => $data['city'],
                'birth_date' => $data['birth_date'],
                'religion' => $data['religion'],
                'marital_status' => $data['marital_status'],
                'linkedin_url' => $data['linkedin_url'] ?? null,
                'portfolio_url' => $data['portfolio_url'] ?? null,
            ]);
        });

        return $this->saved(__('Data diri disimpan.'));
    }

    public function storeEducation(EducationRequest $request): RedirectResponse
    {
        $this->profile($request)->educations()->create($request->validated());

        return $this->saved(__('Riwayat pendidikan ditambahkan.'));
    }

    public function updateEducation(EducationRequest $request, CandidateEducation $education): RedirectResponse
    {
        $this->ensureOwned($request, $education);
        $education->update($request->validated());

        return $this->saved(__('Riwayat pendidikan diperbarui.'));
    }

    public function destroyEducation(Request $request, CandidateEducation $education): RedirectResponse
    {
        $this->ensureOwned($request, $education);
        $education->delete();

        return $this->saved(__('Riwayat pendidikan dihapus.'));
    }

    public function storeExperience(ExperienceRequest $request): RedirectResponse
    {
        $this->profile($request)->experiences()->create($request->experienceData());

        return $this->saved(__('Pengalaman kerja ditambahkan.'));
    }

    public function updateExperience(ExperienceRequest $request, CandidateExperience $experience): RedirectResponse
    {
        $this->ensureOwned($request, $experience);
        $experience->update($request->experienceData());

        return $this->saved(__('Pengalaman kerja diperbarui.'));
    }

    public function destroyExperience(Request $request, CandidateExperience $experience): RedirectResponse
    {
        $this->ensureOwned($request, $experience);
        $experience->delete();

        return $this->saved(__('Pengalaman kerja dihapus.'));
    }

    public function storeDocument(DocumentRequest $request, StoreCandidateDocument $store): RedirectResponse
    {
        $store(
            $this->profile($request),
            CandidateDocumentType::from($request->validated('type')),
            $request->file('file'),
            $request->validated('label'),
        );

        return $this->saved(__('Dokumen diunggah.'));
    }

    public function destroyDocument(CandidateDocument $document, RemoveCandidateDocument $remove): RedirectResponse
    {
        $this->authorize('delete', $document);
        $remove($document);

        return $this->saved(__('Dokumen dihapus.'));
    }

    private function profile(Request $request): CandidateProfile
    {
        return $request->user()->candidateProfile()->firstOrFail();
    }

    /**
     * Data milik kandidat lain diperlakukan seperti tidak ada (404).
     */
    private function ensureOwned(Request $request, Model $entry): void
    {
        abort_unless($entry->getAttribute('candidate_profile_id') === $this->profile($request)->id, 404);
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
