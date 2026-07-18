<?php

namespace App\Modules\Department\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Department\Models\Department;
use App\Modules\Department\Requests\StoreDepartmentRequest;
use App\Modules\Department\Requests\UpdateDepartmentRequest;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::with('company')->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $departments,
        ]);
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = Department::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Department berhasil dibuat',
            'data' => $department->load('company'),
        ], 201);
    }

    public function show(Department $department)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $department->load('company'),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Department berhasil diperbarui',
            'data' => $department->load('company'),
        ]);
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department berhasil dihapus',
            'data' => null,
        ]);
    }
}