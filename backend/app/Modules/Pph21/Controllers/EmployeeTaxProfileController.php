<?php

namespace App\Modules\Pph21\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\Pph21\Models\EmployeePtkpStatus;
use App\Modules\Pph21\Models\EmployeeTaxProfile;
use App\Modules\Pph21\Requests\StoreEmployeePtkpStatusRequest;
use App\Modules\Pph21\Requests\UpdateEmployeeTaxProfileRequest;

class EmployeeTaxProfileController extends Controller
{
    public function index()
    {
        $profiles = EmployeeTaxProfile::with('employee')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $profiles]);
    }

    public function show(Employee $employee)
    {
        $profile = EmployeeTaxProfile::where('employee_id', $employee->id)->first();
        $ptkpHistory = EmployeePtkpStatus::where('employee_id', $employee->id)->orderByDesc('tax_year')->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['profile' => $profile, 'ptkp_history' => $ptkpHistory],
        ]);
    }

    public function update(UpdateEmployeeTaxProfileRequest $request, Employee $employee)
    {
        $profile = EmployeeTaxProfile::updateOrCreate(
            ['employee_id' => $employee->id],
            $request->validated(),
        );

        return response()->json(['success' => true, 'message' => 'Tax profile berhasil disimpan', 'data' => $profile]);
    }

    /**
     * Mirror "PTKP Status Adjustment" Talenta — bikin baris baru utk tax_year
     * tertentu, TIDAK menimpa histori tahun-tahun sebelumnya.
     */
    public function storePtkpStatus(StoreEmployeePtkpStatusRequest $request, Employee $employee)
    {
        $taxYear = (int) $request->validated('tax_year');

        $status = EmployeePtkpStatus::updateOrCreate(
            ['employee_id' => $employee->id, 'tax_year' => $taxYear],
            [
                'ptkp_status' => $request->validated('ptkp_status'),
                'effective_date' => "{$taxYear}-01-01",
            ],
        );

        return response()->json(['success' => true, 'message' => 'Status PTKP berhasil disimpan', 'data' => $status], 201);
    }
}