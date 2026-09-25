<?php

namespace App\Modules\Training\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Models\TrainingParticipant;
use App\Modules\Training\Models\TrainingProgram;
use App\Modules\Training\Models\TrainingSession;
use App\Modules\Training\Requests\StoreTrainingParticipantRequest;
use App\Modules\Training\Requests\UpdateTrainingParticipantRequest;
use Illuminate\Http\JsonResponse;

/**
 * Authorization SAMA dengan update() program induk sesi ini (bukan
 * resource independen) -- pola sama seperti TrainingSessionController.
 */
class TrainingParticipantController extends Controller
{
    public function store(StoreTrainingParticipantRequest $request, TrainingProgram $trainingProgram, TrainingSession $session): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        abort_unless($session->training_program_id === $trainingProgram->id, 404);

        $data = $request->validated();

        $duplicate = $session->participants()->where('employee_id', $data['employee_id'])->exists();
        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan ini sudah terdaftar di sesi ini.',
                'data' => null,
            ], 422);
        }

        if ($session->quota !== null && $session->participants()->count() >= $session->quota) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota sesi ini sudah penuh.',
                'data' => null,
            ], 422);
        }

        $participant = $session->participants()->create([
            ...$data,
            'registered_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Peserta berhasil didaftarkan',
            'data' => $participant->load('employee'),
        ], 201);
    }

    public function update(
        UpdateTrainingParticipantRequest $request,
        TrainingProgram $trainingProgram,
        TrainingSession $session,
        TrainingParticipant $participant,
    ): JsonResponse {
        $this->authorize('update', $trainingProgram);

        abort_unless($session->training_program_id === $trainingProgram->id, 404);
        abort_unless($participant->training_session_id === $session->id, 404);

        $participant->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Data peserta berhasil diperbarui',
            'data' => $participant->fresh()->load('employee'),
        ]);
    }

    public function destroy(TrainingProgram $trainingProgram, TrainingSession $session, TrainingParticipant $participant): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        abort_unless($session->training_program_id === $trainingProgram->id, 404);
        abort_unless($participant->training_session_id === $session->id, 404);

        $participant->delete();

        return response()->json(['success' => true, 'message' => 'Peserta berhasil dihapus dari sesi', 'data' => null]);
    }
}