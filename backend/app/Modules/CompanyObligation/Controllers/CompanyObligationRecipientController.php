<?php

namespace App\Modules\CompanyObligation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanyObligation\Enums\CompanyObligationRecipientType;
use App\Modules\CompanyObligation\Models\CompanyObligation;
use App\Modules\CompanyObligation\Models\CompanyObligationRecipient;
use App\Modules\CompanyObligation\Requests\StoreCompanyObligationRecipientRequest;
use Illuminate\Http\JsonResponse;

/**
 * Kelola siapa yang di-notify reminder sebuah obligation (User/Pic/Role).
 * Authorization SAMA dengan edit obligation induknya (edit company
 * obligations) -- recipient bukan resource independen, dia bagian dari
 * konfigurasi obligation-nya.
 */
class CompanyObligationRecipientController extends Controller
{
    public function store(StoreCompanyObligationRecipientRequest $request, CompanyObligation $companyObligation): JsonResponse
    {
        $this->authorize('update', $companyObligation);

        $data = $request->validated();

        $duplicate = $companyObligation->recipients()
            ->where('recipient_type', $data['recipient_type'])
            ->where('user_id', $data['user_id'] ?? null)
            ->where('role', $data['role'] ?? null)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient ini sudah terdaftar untuk obligation ini.',
                'data' => null,
            ], 422);
        }

        $recipient = $companyObligation->recipients()->create([
            'recipient_type' => $data['recipient_type'],
            'user_id' => $data['recipient_type'] === CompanyObligationRecipientType::User->value ? $data['user_id'] : null,
            'role' => $data['recipient_type'] === CompanyObligationRecipientType::Role->value ? $data['role'] : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recipient reminder berhasil ditambahkan',
            'data' => $recipient->load('user'),
        ], 201);
    }

    public function destroy(CompanyObligation $companyObligation, CompanyObligationRecipient $recipient): JsonResponse
    {
        $this->authorize('update', $companyObligation);

        abort_unless($recipient->company_obligation_id === $companyObligation->id, 404);

        $recipient->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recipient reminder berhasil dihapus',
            'data' => null,
        ]);
    }
}