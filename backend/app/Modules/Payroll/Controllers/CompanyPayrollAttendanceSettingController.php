<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\CompanyPayrollAttendanceSetting;
use App\Modules\Payroll\Requests\UpdateCompanyPayrollAttendanceSettingRequest;
use Illuminate\Http\Request;

class CompanyPayrollAttendanceSettingController extends Controller
{
    public function show(Request $request)
    {
        $setting = CompanyPayrollAttendanceSetting::where('company_id', $request->query('company_id'))->first();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $setting]);
    }

    public function update(UpdateCompanyPayrollAttendanceSettingRequest $request)
    {
        $setting = CompanyPayrollAttendanceSetting::updateOrCreate(
            ['company_id' => $request->validated('company_id')],
            $request->validated(),
        );

        return response()->json(['success' => true, 'message' => 'Pengaturan attendance-payroll berhasil disimpan', 'data' => $setting]);
    }
}