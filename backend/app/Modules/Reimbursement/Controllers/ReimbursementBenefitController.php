<?php

namespace App\Modules\Reimbursement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reimbursement\Models\ReimbursementBenefit;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use App\Modules\Reimbursement\Requests\StoreReimbursementBenefitRequest;
use App\Modules\Reimbursement\Requests\UpdateReimbursementBenefitRequest;

class ReimbursementBenefitController extends Controller
{
    public function index(ReimbursementPolicy $reimbursementPolicy)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reimbursementPolicy->benefits()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(
        StoreReimbursementBenefitRequest $request,
        ReimbursementPolicy $reimbursementPolicy
    ) {
        $benefit = $reimbursementPolicy
            ->benefits()
            ->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Benefit berhasil ditambahkan',
            'data' => $benefit,
        ], 201);
    }

    public function update(
        UpdateReimbursementBenefitRequest $request,
        ReimbursementBenefit $reimbursementBenefit
    ) {
        $reimbursementBenefit->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Benefit berhasil diperbarui',
            'data' => $reimbursementBenefit->fresh(),
        ]);
    }
}