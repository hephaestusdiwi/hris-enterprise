<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\NonRegularPayrollComponent;
use App\Modules\Payroll\Requests\StoreNonRegularPayrollComponentRequest;
use App\Modules\Payroll\Requests\UpdateNonRegularPayrollComponentRequest;
use Illuminate\Http\Request;

class NonRegularPayrollComponentController extends Controller
{
    public function index(Request $request)
    {
        $components = NonRegularPayrollComponent::with('company')
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $components]);
    }

    public function store(StoreNonRegularPayrollComponentRequest $request)
    {
        $component = NonRegularPayrollComponent::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Komponen payroll non-reguler berhasil dibuat',
            'data' => $component->load('company'),
        ], 201);
    }

    public function show(NonRegularPayrollComponent $nonRegularPayrollComponent)
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $nonRegularPayrollComponent->load('company')]);
    }

    public function update(UpdateNonRegularPayrollComponentRequest $request, NonRegularPayrollComponent $nonRegularPayrollComponent)
    {
        $nonRegularPayrollComponent->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Komponen payroll non-reguler berhasil diperbarui',
            'data' => $nonRegularPayrollComponent->load('company'),
        ]);
    }

    public function destroy(NonRegularPayrollComponent $nonRegularPayrollComponent)
    {
        $nonRegularPayrollComponent->delete();

        return response()->json(['success' => true, 'message' => 'Komponen payroll non-reguler berhasil dihapus', 'data' => null]);
    }
}