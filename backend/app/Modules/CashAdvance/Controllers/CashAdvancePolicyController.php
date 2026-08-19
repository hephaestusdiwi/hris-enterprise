<?php

namespace App\Modules\CashAdvance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Requests\StoreCashAdvancePolicyRequest;
use App\Modules\CashAdvance\Requests\UpdateCashAdvancePolicyRequest;

class CashAdvancePolicyController extends Controller
{
    public function index()
    {
        $policies = CashAdvancePolicy::with('categories')->orderByDesc('id')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $policies]);
    }

    public function store(StoreCashAdvancePolicyRequest $request)
    {
        $policy = CashAdvancePolicy::create([
            ...$request->safe()->except('category_ids'),
            'created_by_user_id' => $request->user()?->id,
        ]);

        if ($request->validated('category_ids')) {
            $policy->categories()->sync($request->validated('category_ids'));
        }

        return response()->json(['success' => true, 'message' => 'Policy berhasil dibuat', 'data' => $policy->load('categories')], 201);
    }

    public function update(UpdateCashAdvancePolicyRequest $request, CashAdvancePolicy $cashAdvancePolicy)
    {
        $cashAdvancePolicy->update($request->safe()->except('category_ids'));

        if ($request->has('category_ids')) {
            $cashAdvancePolicy->categories()->sync($request->validated('category_ids') ?? []);
        }

        return response()->json(['success' => true, 'message' => 'Policy berhasil diperbarui', 'data' => $cashAdvancePolicy->fresh('categories')]);
    }
}