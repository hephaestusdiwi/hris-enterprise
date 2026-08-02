<?php

namespace App\Modules\EmployeeAllowance\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmployeeAllowance\Enums\EmployeeAllowanceStatus;
use App\Modules\EmployeeAllowance\Exceptions\EmployeeAllowanceException;
use App\Modules\EmployeeAllowance\Exports\EmployeeAllowanceExport;
use App\Modules\EmployeeAllowance\Imports\EmployeeAllowanceImport;
use App\Modules\EmployeeAllowance\Models\EmployeeAllowance;
use App\Modules\EmployeeAllowance\Requests\StoreEmployeeAllowanceRequest;
use App\Modules\EmployeeAllowance\Requests\UpdateEmployeeAllowanceRequest;
use App\Modules\EmployeeAllowance\Requests\VoidEmployeeAllowanceRequest;
use App\Modules\EmployeeAllowance\Services\EmployeeAllowanceService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeAllowanceController extends Controller
{
    public function __construct(private EmployeeAllowanceService $service)
    {
    }

    private function applyFilters(Request $request)
    {
        return EmployeeAllowance::with(['employee.department', 'salaryComponent', 'createdBy'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('department_id'), fn ($q, $v) => $q->whereHas('employee', fn ($eq) => $eq->where('department_id', $v)))
            ->when($request->query('payroll_period_year'), fn ($q, $v) => $q->where('payroll_period_year', $v))
            ->when($request->query('payroll_period_month'), fn ($q, $v) => $q->where('payroll_period_month', $v))
            ->when($request->query('salary_component_id'), fn ($q, $v) => $q->where('salary_component_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
    }

    public function index(Request $request)
    {
        $allowances = $this->applyFilters($request)->latest()->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $allowances]);
    }

    public function summary(Request $request)
    {
        $base = $this->applyFilters($request);

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'draft' => (int) ($counts[EmployeeAllowanceStatus::Draft->value] ?? 0),
                'ready' => (int) ($counts[EmployeeAllowanceStatus::Ready->value] ?? 0),
                'processed' => (int) ($counts[EmployeeAllowanceStatus::Processed->value] ?? 0),
                'void' => (int) ($counts[EmployeeAllowanceStatus::Void->value] ?? 0),
            ],
        ]);
    }

    public function store(StoreEmployeeAllowanceRequest $request)
    {
        $allowance = $this->service->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Allowance berhasil dibuat',
            'data' => $allowance->load(['employee', 'salaryComponent', 'createdBy']),
        ], 201);
    }

    public function show(EmployeeAllowance $employeeAllowance)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employeeAllowance->load(['employee', 'salaryComponent', 'createdBy', 'voidedBy']),
        ]);
    }

    public function update(UpdateEmployeeAllowanceRequest $request, EmployeeAllowance $employeeAllowance)
    {
        try {
            $allowance = $this->service->update($employeeAllowance, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Allowance berhasil diperbarui',
                'data' => $allowance->load(['employee', 'salaryComponent', 'createdBy']),
            ]);
        } catch (EmployeeAllowanceException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function void(VoidEmployeeAllowanceRequest $request, EmployeeAllowance $employeeAllowance)
    {
        try {
            $allowance = $this->service->void($employeeAllowance, $request->validated('reason'), $request->user());

            return response()->json(['success' => true, 'message' => 'Allowance berhasil di-void', 'data' => $allowance]);
        } catch (EmployeeAllowanceException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function bulkMarkReady(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1']])['ids'];

        $updated = 0;
        $failed = [];

        foreach (EmployeeAllowance::whereIn('id', $ids)->get() as $allowance) {
            try {
                $this->service->markReady($allowance);
                $updated++;
            } catch (EmployeeAllowanceException $e) {
                $failed[] = ['id' => $allowance->id, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'message' => "{$updated} allowance berhasil ditandai Ready", 'data' => ['updated' => $updated, 'failed' => $failed]]);
    }

    public function bulkVoid(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'reason' => ['required', 'string'],
        ]);

        $updated = 0;
        $failed = [];

        foreach (EmployeeAllowance::whereIn('id', $validated['ids'])->get() as $allowance) {
            try {
                $this->service->void($allowance, $validated['reason'], $request->user());
                $updated++;
            } catch (EmployeeAllowanceException $e) {
                $failed[] = ['id' => $allowance->id, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'message' => "{$updated} allowance berhasil di-void", 'data' => ['updated' => $updated, 'failed' => $failed]]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:5120']]);

        $import = new EmployeeAllowanceImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => count($import->created).' allowance berhasil diimport, '.count($import->errors).' baris gagal',
            'data' => ['created' => count($import->created), 'errors' => $import->errors],
        ], 201);
    }

    public function export(Request $request)
    {
        $allowances = $this->applyFilters($request)->get();
        $filename = 'employee-allowances-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new EmployeeAllowanceExport($allowances), $filename);
    }
}