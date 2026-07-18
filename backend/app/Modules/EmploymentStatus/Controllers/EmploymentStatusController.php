<?php

namespace App\Modules\EmploymentStatus\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmploymentStatus\Models\EmploymentStatus;
use App\Modules\EmploymentStatus\Requests\StoreEmploymentStatusRequest;
use App\Modules\EmploymentStatus\Requests\UpdateEmploymentStatusRequest;

class EmploymentStatusController extends Controller
{
    public function index()
    {
        $statuses = EmploymentStatus::latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $statuses,
        ]);
    }

    public function store(StoreEmploymentStatusRequest $request)
    {
        $status = EmploymentStatus::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employment status berhasil dibuat',
            'data' => $status,
        ], 201);
    }

    public function show(EmploymentStatus $employmentStatus)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employmentStatus,
        ]);
    }

    public function update(UpdateEmploymentStatusRequest $request, EmploymentStatus $employmentStatus)
    {
        $employmentStatus->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employment status berhasil diperbarui',
            'data' => $employmentStatus,
        ]);
    }

    public function destroy(EmploymentStatus $employmentStatus)
    {
        $employmentStatus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employment status berhasil dihapus',
            'data' => null,
        ]);
    }
}