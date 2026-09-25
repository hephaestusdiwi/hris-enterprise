<?php

namespace App\Modules\Training\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Enums\TrainingRecipientType;
use App\Modules\Training\Models\TrainingProgram;
use App\Modules\Training\Models\TrainingRecipient;
use App\Modules\Training\Requests\StoreTrainingRecipientRequest;
use Illuminate\Http\JsonResponse;

/**
 * Recipient TAMBAHAN di luar peserta -- lihat komentar migration
 * training_recipients. Authorization sama dengan edit program induk
 * (pola persis CompanyObligationRecipientController).
 */
class TrainingRecipientController extends Controller
{
    public function store(StoreTrainingRecipientRequest $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        $data = $request->validated();

        $duplicate = $trainingProgram->recipients()
            ->where('recipient_type', $data['recipient_type'])
            ->where('user_id', $data['user_id'] ?? null)
            ->where('role', $data['role'] ?? null)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient ini sudah terdaftar untuk training ini.',
                'data' => null,
            ], 422);
        }

        $recipient = $trainingProgram->recipients()->create([
            'recipient_type' => $data['recipient_type'],
            'user_id' => $data['recipient_type'] === TrainingRecipientType::User->value ? $data['user_id'] : null,
            'role' => $data['recipient_type'] === TrainingRecipientType::Role->value ? $data['role'] : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recipient reminder berhasil ditambahkan',
            'data' => $recipient->load('user'),
        ], 201);
    }

    public function destroy(TrainingProgram $trainingProgram, TrainingRecipient $recipient): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        abort_unless($recipient->training_program_id === $trainingProgram->id, 404);

        $recipient->delete();

        return response()->json(['success' => true, 'message' => 'Recipient reminder berhasil dihapus', 'data' => null]);
    }
}