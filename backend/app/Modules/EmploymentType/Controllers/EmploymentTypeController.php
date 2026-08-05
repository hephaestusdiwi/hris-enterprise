<?php

namespace App\Modules\EmploymentType\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmploymentType\Models\EmploymentType;
use App\Modules\EmploymentType\Requests\StoreEmploymentTypeRequest;
use App\Modules\EmploymentType\Requests\UpdateEmploymentTypeRequest;

class EmploymentTypeController extends Controller
{
    public function index()
    {
        $types = EmploymentType::latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $types,
        ]);
    }

    public function store(StoreEmploymentTypeRequest $request)
    {
        $type = EmploymentType::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employment type berhasil dibuat',
            'data' => $type,
        ], 201);
    }

    public function show(EmploymentType $employmentType)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employmentType,
        ]);
    }

    public function update(
        UpdateEmploymentTypeRequest $request,
        EmploymentType $employmentType
    ) {
        $employmentType->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employment type berhasil diperbarui',
            'data' => $employmentType,
        ]);
    }

    public function destroy(EmploymentType $employmentType)
    {
        $employmentType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employment type berhasil dihapus',
            'data' => null,
        ]);
    }
}