<?php

namespace App\Modules\EmployeeSalary\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryResolverInterface;
use App\Modules\EmployeeSalary\Contracts\EmployeeSalaryScopeInterface;
use App\Modules\EmployeeSalary\Models\EmployeeSalary;
use App\Modules\EmployeeSalary\Requests\StoreEmployeeSalaryRequest;
use App\Modules\EmployeeSalary\Requests\PreviewEmployeeSalaryRequest;
use App\Modules\EmployeeSalary\Models\EmployeeSalaryOverride;
use App\Modules\Employee\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeSalaryController extends Controller
{
    public function __construct(
        private EmployeeSalaryResolverInterface $resolver,
        private EmployeeSalaryScopeInterface $employeeSalaryScope,
    ){
    }

    public function index(Request $request)
    {
        $salaries = $this->employeeSalaryScope
            ->apply(
                EmployeeSalary::with(['employee', 'overrides.salaryComponent'])
                    ->when($request->query('employee_id'), fn ($q, $v) => $q->where('employee_id', $v))
                    ->orderByDesc('effective_date'),
                $request->user(),
            )
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $salaries]);
    }

    public function store(StoreEmployeeSalaryRequest $request)
    {
        $validated = $request->validated();

        $employeeSalary = DB::transaction(function () use ($validated) {
            $employeeSalary = EmployeeSalary::create([
                'employee_id' => $validated['employee_id'],
                'salary_structure_code' => $validated['salary_structure_code'],
                'effective_date' => $validated['effective_date'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['overrides'] ?? [] as $override) {
                $employeeSalary->overrides()->create($override);
            }

            return $employeeSalary;
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee Salary berhasil dibuat',
            'data' => $employeeSalary->load(['employee', 'overrides.salaryComponent']),
        ], 201);
    }

    public function show(EmployeeSalary $employeeSalary)
    {
        $this->authorize('view', $employeeSalary);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employeeSalary->load(['employee', 'overrides.salaryComponent']),
        ]);
    }

    public function resolved(Request $request, Employee $employee)
    {
        $referenceDate = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();

        $lines = $this->resolver->resolveComponents($employee, $referenceDate);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => array_map(fn ($line) => [
                'component' => [
                    'id' => $line->component->id,
                    'name' => $line->component->name,
                    'category' => $line->component->category->value,
                    'is_addition' => $line->component->is_addition,
                ],
                'amount' => $line->amount,
                'percentage_value' => $line->percentageValue,
                'percentage_base' => $line->percentageBase,
                'source' => $line->source,
            ], $lines),
        ]);
    }

    public function preview(PreviewEmployeeSalaryRequest $request, Employee $employee)
    {
        $validated = $request->validated();

        $draftOverrides = collect($validated['overrides'] ?? [])
            ->map(fn ($o) => new EmployeeSalaryOverride($o));

        $lines = $this->resolver->resolvePreview(
            $employee,
            Carbon::parse($validated['effective_date']),
            $validated['salary_structure_code'],
            $draftOverrides
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => array_map(fn ($line) => [
                'component' => [
                    'id' => $line->component->id,
                    'name' => $line->component->name,
                    'category' => $line->component->category->value,
                    'is_addition' => $line->component->is_addition,
                ],
                'amount' => $line->amount,
                'percentage_value' => $line->percentageValue,
                'percentage_base' => $line->percentageBase,
                'source' => $line->source,
            ], $lines),
        ]);
    }

    public function destroy(EmployeeSalary $employeeSalary)
    {
        $this->authorize('delete', $employeeSalary);
        // TODO (STEP 54 - Payroll Generator): tolak kalau assignment ini pernah dipakai
        // menghasilkan payroll manapun.
        $employeeSalary->delete();

        return response()->json(['success' => true, 'message' => 'Employee Salary berhasil dihapus', 'data' => null]);
    }
}