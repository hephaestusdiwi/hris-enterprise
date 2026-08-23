<?php

namespace App\Modules\JobVacancy\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Candidate\Enums\CandidateSource;
use App\Modules\Candidate\Exceptions\CandidateValidationException;
use App\Modules\Candidate\Requests\ApplyInternalJobVacancyRequest;
use App\Modules\Candidate\Services\CandidateService;
use App\Modules\JobVacancy\Enums\JobVacancyStatus;
use App\Modules\JobVacancy\Enums\VacancyVisibility;
use App\Modules\JobVacancy\Models\JobVacancy;
use App\Modules\JobVacancy\Resources\JobVacancyPublicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class JobVacancySelfServiceController extends Controller
{
    public function __construct(
        private CandidateService $candidateService,
    ) {
    }

    private function baseQuery()
    {
        return JobVacancy::query()
            ->where('status', JobVacancyStatus::Published->value)
            ->whereIn('visibility', [VacancyVisibility::Internal->value, VacancyVisibility::Both->value])
            ->with(['employmentType', 'company', 'branch', 'department']);
    }

    public function index(): AnonymousResourceCollection
    {
        return JobVacancyPublicResource::collection($this->baseQuery()->latest('published_at')->paginate());
    }

    public function show(string $slug): JobVacancyPublicResource
    {
        return new JobVacancyPublicResource($this->baseQuery()->where('slug', $slug)->firstOrFail());
    }

    public function apply(string $slug, ApplyInternalJobVacancyRequest $request): JsonResponse
    {
        $vacancy = $this->baseQuery()->where('slug', $slug)->firstOrFail();
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda belum terhubung dengan data Employee.',
                'data' => null,
            ], 422);
        }

        try {
            $cvPath = $request->file('cv')->store('candidate-cvs', 'private');

            $candidate = $this->candidateService->apply($vacancy, [
                // Identitas dari profil Employee yang login — BUKAN dari input client,
                // supaya karyawan tidak bisa melamar mengatasnamakan orang lain.
                'full_name' => trim($employee->first_name.' '.($employee->last_name ?? '')),
                'email' => $employee->personal_email ?? $request->user()->email,
                'phone' => $employee->phone ?? '',
                'source' => CandidateSource::Internal->value,
                'cv_path' => $cvPath,
            ]);
        } catch (CandidateValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lamaran internal berhasil dikirim.',
            'data' => ['id' => $candidate->id, 'status' => $candidate->status->value],
        ], 201);
    }
}