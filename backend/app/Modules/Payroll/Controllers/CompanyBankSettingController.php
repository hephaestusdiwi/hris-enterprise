<?php

namespace App\Modules\Payroll\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Models\CompanyBankSetting;
use App\Modules\Payroll\Requests\UpdateCompanyBankSettingRequest;
use Illuminate\Http\Request;

class CompanyBankSettingController extends Controller
{
    public function show(Request $request)
    {
        $setting = CompanyBankSetting::where('company_id', $request->query('company_id'))->first();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $setting]);
    }

    public function update(UpdateCompanyBankSettingRequest $request)
    {
        $setting = CompanyBankSetting::updateOrCreate(
            ['company_id' => $request->validated('company_id')],
            $request->validated(),
        );

        return response()->json(['success' => true, 'message' => 'Rekening sumber payroll berhasil disimpan', 'data' => $setting]);
    }
}
