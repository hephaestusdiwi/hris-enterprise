<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Requests\StoreCashAdvancePolicyRequest;
use App\Modules\CashAdvance\Requests\UpdateCashAdvancePolicyRequest;
use Illuminate\Http\Request;

class CashAdvancePolicyController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => CashAdvancePolicy::query()
                ->with('categories')
                ->latest()
                ->get(),
        ]);
    }

    public function store(StoreCashAdvancePolicyRequest $request)
    {
        $policy = CashAdvancePolicy::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()->id,
        ]);

        $policy->categories()->sync(
            $request->validated('category_ids', [])
        );

        return response()->json([
            'success' => true,
            'message' => 'Policy berhasil dibuat',
            'data' => $policy->load('categories'),
        ], 201);
    }

    public function update(
        UpdateCashAdvancePolicyRequest $request,
        CashAdvancePolicy $cashAdvancePolicy,
    ) {
        $cashAdvancePolicy->update(
            $request->safe()->except('category_ids')
        );

        if ($request->has('category_ids')) {
            $cashAdvancePolicy->categories()->sync(
                $request->validated('category_ids', [])
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Policy berhasil diperbarui',
            'data' => $cashAdvancePolicy->fresh()->load('categories'),
        ]);
    }
}