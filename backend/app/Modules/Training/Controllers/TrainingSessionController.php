<?php

namespace App\Modules\Training\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Enums\TrainingSessionStatus;
use App\Modules\Training\Models\TrainingProgram;
use App\Modules\Training\Models\TrainingSession;
use App\Modules\Training\Requests\StoreTrainingSessionRequest;
use App\Modules\Training\Requests\UpdateTrainingSessionRequest;
use Illuminate\Http\JsonResponse;

/**
 * Session BUKAN resource independen -- authorization SAMA dengan
 * update() program induknya (pola sama seperti
 * CompanyObligationRecipientController terhadap CompanyObligation).
 */
class TrainingSessionController extends Controller
{
    public function store(StoreTrainingSessionRequest $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        $session = $trainingProgram->sessions()->create([
            ...$request->validated(),
            'status' => TrainingSessionStatus::Scheduled,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sesi Training berhasil ditambahkan',
            'data' => $session,
        ], 201);
    }

    public function update(UpdateTrainingSessionRequest $request, TrainingProgram $trainingProgram, TrainingSession $session): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        abort_unless($session->training_program_id === $trainingProgram->id, 404);

        $session->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Sesi Training berhasil diperbarui',
            'data' => $session->fresh(),
        ]);
    }

    public function destroy(TrainingProgram $trainingProgram, TrainingSession $session): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        abort_unless($session->training_program_id === $trainingProgram->id, 404);

        $session->delete();

        return response()->json(['success' => true, 'message' => 'Sesi Training berhasil dihapus', 'data' => null]);
    }
}