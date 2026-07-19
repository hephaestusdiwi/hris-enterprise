<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Employee\Requests\StoreEmployeeRequest;
use App\Modules\Employee\Requests\UpdateEmployeeRequest;

class EmployeeController extends Controller
{
    protected array $relations = ['company', 'branch', 'department', 'position', 'jobLevel', 'employmentStatus', 'manager', 'user'];

    public function index()
    {
        $employees = Employee::with($this->relations)->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employees,
        ]);
    }

    public function nextNumber()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['employee_number' => $this->generateEmployeeNumber()],
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $employee = Employee::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil dibuat',
            'data' => $employee->load($this->relations),
        ], 201);
    }

    public function show(Employee $employee)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->load($this->relations),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $employee->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil diperbarui',
            'data' => $employee->load($this->relations),
        ]);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil dihapus',
            'data' => null,
        ]);
    }

    private function generateEmployeeNumber(): string
    {
        $year = date('Y');
        $prefix = "EMP-{$year}-";

        $last = Employee::withTrashed()
            ->where('employee_number', 'like', $prefix.'%')
            ->orderByDesc('employee_number')
            ->first();

        $next = 1;
        if ($last) {
            $next = (int) substr($last->employee_number, strlen($prefix)) + 1;
        }

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }


    // cuma buat foto
    public function orgChart()
    {
        $employees = Employee::with(['position:id,name'])
            ->whereNull('resign_date')
            ->get(['id', 'first_name', 'last_name', 'manager_employee_id', 'position_id']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $this->buildOrgTree($employees),
        ]);
    }

    private function buildOrgTree($employees, ?int $managerId = null): array
    {
        return $employees
            ->where('manager_employee_id', $managerId)
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'position' => $employee->position?->name,
                'children' => $this->buildOrgTree($employees, $employee->id),
            ])
            ->values()
            ->all();
    }
}