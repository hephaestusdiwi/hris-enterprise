<?php

namespace App\Modules\SalaryComponent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalaryComponent\Enums\CalculationMethod;
use App\Modules\SalaryComponent\Models\SalaryComponent;
use App\Modules\SalaryComponent\Requests\StoreSalaryComponentRequest;
use App\Modules\SalaryComponent\Requests\UpdateSalaryComponentRequest;

class SalaryComponentController extends Controller
{
    public function index()
    {
        $components = SalaryComponent::with('company')->latest()->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $components]);
    }

    public function store(StoreSalaryComponentRequest $request)
    {
        $component = SalaryComponent::create($this->normalize($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Salary Component berhasil dibuat',
            'data' => $component->load('company'),
        ], 201);
    }

    public function show(SalaryComponent $salaryComponent)
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $salaryComponent->load('company')]);
    }

    public function update(UpdateSalaryComponentRequest $request, SalaryComponent $salaryComponent)
    {
        $salaryComponent->update($this->normalize($request->validated()));

        return response()->json([
            'success' => true,
            'message' => 'Salary Component berhasil diperbarui',
            'data' => $salaryComponent->load('company'),
        ]);
    }

    public function destroy(SalaryComponent $salaryComponent)
    {
        $salaryComponent->delete();

        return response()->json(['success' => true, 'message' => 'Salary Component berhasil dihapus', 'data' => null]);
    }

    private function normalize(array $data): array
    {
        if (($data['calculation_method'] ?? null) === CalculationMethod::Fixed->value) {
            $data['percentage_value'] = null;
            $data['percentage_base'] = null;
        } elseif (($data['calculation_method'] ?? null) === CalculationMethod::Percentage->value) {
            $data['amount'] = null;
        }

        return $data;
    }
}