<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Exceptions\EmployeeNonRegularInputException;
use App\Modules\Payroll\Models\EmployeeNonRegularInput;
use App\Modules\Payroll\Requests\StoreEmployeeNonRegularInputRequest;
use App\Modules\Payroll\Requests\UpdateEmployeeNonRegularInputRequest;
use App\Modules\Payroll\Services\EmployeeNonRegularInputService;
use Illuminate\Http\Request;

class EmployeeNonRegularInputController extends Controller
{
    public function __construct(private EmployeeNonRegularInputService $service)
    {
    }

    private function applyFilters(Request $request)
    {
        return EmployeeNonRegularInput::with(['employee.department', 'component', 'createdBy'])
            ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
            ->when($request->query('company_id'), fn ($q, $v) => $q->whereHas('employee', fn ($eq) => $eq->where('company_id', $v)))
            ->when($request->query('non_regular_payroll_component_id'), fn ($q, $v) => $q->where('non_regular_payroll_component_id', $v))
            ->when($request->query('payroll_period_year'), fn ($q, $v) => $q->where('payroll_period_year', $v))
            ->when($request->query('payroll_period_month'), fn ($q, $v) => $q->where('payroll_period_month', $v))
            ->when($request->query('payroll_run_id'), fn ($q, $v) => $q->where('payroll_run_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
    }

    public function index(Request $request)
    {
        $inputs = $this->applyFilters($request)->latest()->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $inputs]);
    }

    public function store(StoreEmployeeNonRegularInputRequest $request)
    {
        try {
            $input = $this->service->create([
                ...$request->validated(),
                'is_addition' => $request->resolvedIsAddition(),
            ], $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Input payroll non-reguler berhasil dibuat',
                'data' => $input->load(['employee', 'component', 'createdBy']),
            ], 201);
        } catch (EmployeeNonRegularInputException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function show(EmployeeNonRegularInput $employeeNonRegularInput)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employeeNonRegularInput->load(['employee', 'component', 'createdBy', 'voidedBy', 'payrollRun']),
        ]);
    }

    public function update(UpdateEmployeeNonRegularInputRequest $request, EmployeeNonRegularInput $employeeNonRegularInput)
    {
        try {
            $input = $this->service->update($employeeNonRegularInput, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Input payroll non-reguler berhasil diperbarui',
                'data' => $input->load(['employee', 'component', 'createdBy']),
            ]);
        } catch (EmployeeNonRegularInputException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function void(Request $request, EmployeeNonRegularInput $employeeNonRegularInput)
    {
        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];

        try {
            $input = $this->service->void($employeeNonRegularInput, $reason, $request->user());

            return response()->json(['success' => true, 'message' => 'Input berhasil di-void', 'data' => $input]);
        } catch (EmployeeNonRegularInputException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function markReady(EmployeeNonRegularInput $employeeNonRegularInput)
    {
        try {
            $input = $this->service->markReady($employeeNonRegularInput);

            return response()->json(['success' => true, 'message' => 'Input ditandai Ready', 'data' => $input]);
        } catch (EmployeeNonRegularInputException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function bulkMarkReady(Request $request)
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1']])['ids'];

        $updated = 0;
        $failed = [];

        foreach (EmployeeNonRegularInput::whereIn('id', $ids)->get() as $input) {
            try {
                $this->service->markReady($input);
                $updated++;
            } catch (EmployeeNonRegularInputException $e) {
                $failed[] = ['id' => $input->id, 'message' => $e->getMessage()];
            }
        }

        return response()->json(['success' => true, 'message' => "{$updated} input berhasil ditandai Ready", 'data' => ['updated' => $updated, 'failed' => $failed]]);
    }
}