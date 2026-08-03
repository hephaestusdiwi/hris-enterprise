<?php

namespace App\Modules\EmployeeDeduction\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmployeeDeduction\Enums\EmployeeDeductionStatus;
use App\Modules\EmployeeDeduction\Exceptions\EmployeeDeductionException;
use App\Modules\EmployeeDeduction\Exports\EmployeeDeductionExport;
use App\Modules\EmployeeDeduction\Imports\EmployeeDeductionImport;
use App\Modules\EmployeeDeduction\Models\EmployeeDeduction;
use App\Modules\EmployeeDeduction\Requests\StoreEmployeeDeductionRequest;
use App\Modules\EmployeeDeduction\Requests\UpdateEmployeeDeductionRequest;
use App\Modules\EmployeeDeduction\Requests\VoidEmployeeDeductionRequest;
use App\Modules\EmployeeDeduction\Services\EmployeeDeductionService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeDeductionController extends Controller
{
    public function __construct(private EmployeeDeductionService $service)
    {
    }

    private function applyFilters(Request $request)
    {
        return EmployeeDeduction::with(['employee.department', 'salaryComponent', 'createdBy'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('department_id'), fn ($q, $v) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $v)))
            ->when($request->query('payroll_period_year'), fn ($q, $v) => $q->where('payroll_period_year', $v))
            ->when($request->query('payroll_period_month'), fn ($q, $v) => $q->where('payroll_period_month', $v))
            ->when($request->query('salary_component_id'), fn ($q, $v) => $q->where('salary_component_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
    }

    public function index(Request $request)
    {
        $deductions = $this->applyFilters($request)->latest()->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $deductions]);
    }

    public function summary(Request $request)
    {
        $base = $this->applyFilters($request);
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'draft' => (int) ($counts[EmployeeDeductionStatus::Draft->value] ?? 0),
                'ready' => (int) ($counts[EmployeeDeductionStatus::Ready->value] ?? 0),
                'processed' => (int) ($counts[EmployeeDeductionStatus::Processed->value] ?? 0),
                'void' => (int) ($counts[EmployeeDeductionStatus::Void->value] ?? 0),
            ],
        ]);
    }

    public function store(StoreEmployeeDeductionRequest $request)
    {
        $deduction = $this->service->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Deduction berhasil dibuat',
            'data' => $deduction->load(['employee', 'salaryComponent', 'createdBy']),
        ], 201);
    }

    public function show(EmployeeDeduction $employeeDeduction)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employeeDeduction->load(['employee.department', 'salaryComponent', 'createdBy', 'voidedBy']),
        ]);
    }

    public function update(UpdateEmployeeDeductionRequest $request, EmployeeDeduction $employeeDeduction)
    {
        try {
            $deduction = $this->service->update($employeeDeduction, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Deduction berhasil diperbarui',
                'data' => $deduction->load(['employee', 'salaryComponent', 'createdBy']),
            ]);
        } catch (EmployeeDeductionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function void(VoidEmployeeDeductionRequest $request, EmployeeDeduction $employeeDeduction)
    {
        try {
            $deduction = $this->service->void($employeeDeduction, $request->validated('reason'), $request->user());

            return response()->json(['success' => true, 'message' => 'Deduction berhasil di-void', 'data' => $deduction]);
        } catch (EmployeeDeductionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function bulkMarkReady(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1']])['ids'];

        $updated = 0;
        $failed = [];

        foreach (EmployeeDeduction::whereIn('id', $ids)->get() as $deduction) {
            try {
                $this->service->markReady($deduction);
                $updated++;
            } catch (EmployeeDeductionException $e) {
                $failed[] = ['id' => $deduction->id, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'message' => "{$updated} deduction berhasil ditandai Ready", 'data' => ['updated' => $updated, 'failed' => $failed]]);
    }

    public function bulkVoid(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'reason' => ['required', 'string'],
        ]);

        $updated = 0;
        $failed = [];

        foreach (EmployeeDeduction::whereIn('id', $validated['ids'])->get() as $deduction) {
            try {
                $this->service->void($deduction, $validated['reason'], $request->user());
                $updated++;
            } catch (EmployeeDeductionException $e) {
                $failed[] = ['id' => $deduction->id, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'message' => "{$updated} deduction berhasil di-void", 'data' => ['updated' => $updated, 'failed' => $failed]]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:5120']]);

        $import = new EmployeeDeductionImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => count($import->created).' deduction berhasil diimport, '.count($import->errors).' baris gagal',
            'data' => ['created' => count($import->created), 'errors' => $import->errors],
        ], 201);
    }

    public function export(Request $request)
    {
        $deductions = $this->applyFilters($request)->get();
        $filename = 'employee-deductions-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new EmployeeDeductionExport($deductions), $filename);
    }
}