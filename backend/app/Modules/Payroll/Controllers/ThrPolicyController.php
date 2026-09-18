<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\ThrPolicy;
use App\Modules\Payroll\Requests\StoreThrPolicyRequest;
use App\Modules\Payroll\Requests\UpdateThrPolicyRequest;
use App\Modules\Payroll\Services\ThrEligibilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ThrPolicyController extends Controller
{
    public function __construct(private ThrEligibilityService $eligibilityService)
    {
    }

    public function index(Request $request)
    {
        $policies = ThrPolicy::with('company')
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $policies]);
    }

    public function store(StoreThrPolicyRequest $request)
    {
        $policy = ThrPolicy::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'THR Policy berhasil dibuat',
            'data' => $policy->load('company'),
        ], 201);
    }

    public function show(ThrPolicy $thrPolicy)
    {
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $thrPolicy->load('company')]);
    }

    public function update(UpdateThrPolicyRequest $request, ThrPolicy $thrPolicy)
    {
        $thrPolicy->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'THR Policy berhasil diperbarui',
            'data' => $thrPolicy->load('company'),
        ]);
    }

    public function destroy(ThrPolicy $thrPolicy)
    {
        $thrPolicy->delete();

        return response()->json(['success' => true, 'message' => 'THR Policy berhasil dihapus', 'data' => null]);
    }

    /**
     * Preview employee yang eligible THR per tanggal referensi (default hari
     * ini) berdasarkan policy aktif company — dipakai frontend buat
     * pre-populate daftar participant sebelum bikin THR run. HR tetap bisa
     * menambah/mengurangi dari daftar ini (employee inclusion/exclusion)
     * sebelum submit ke POST /payroll-runs.
     */
    public function eligibleEmployees(Request $request)
    {
        $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'reference_date' => ['nullable', 'date'],
        ]);

        $referenceDate = $request->filled('reference_date') ? Carbon::parse($request->query('reference_date')) : Carbon::now();
        $companyId = (int) $request->query('company_id');

        $policy = $this->eligibilityService->resolveActivePolicy($companyId, $referenceDate);

        if (! $policy) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada THR Policy aktif untuk company ini.',
                'data' => null,
            ], 422);
        }

        $employees = $this->eligibilityService->eligibleEmployees($companyId, $policy, $referenceDate)
            ->map(fn ($employee) => [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => trim($employee->first_name.' '.$employee->last_name),
                'join_date' => $employee->join_date?->toDateString(),
                'service_months' => $this->eligibilityService->serviceMonths($employee, $referenceDate),
                'proration_factor' => $this->eligibilityService->prorationFactor($employee, $policy, $referenceDate),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'policy' => $policy,
                'reference_date' => $referenceDate->toDateString(),
                'employees' => $employees,
            ],
        ]);
    }
}