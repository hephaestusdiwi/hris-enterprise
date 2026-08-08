<?php

namespace App\Modules\Pph21\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pph21\Models\CompanyTaxSetting;
use App\Modules\Pph21\Requests\UpdateCompanyTaxSettingRequest;
use Illuminate\Http\Request;

class CompanyTaxSettingController extends Controller
{
    public function show(Request $request)
    {
        $setting = CompanyTaxSetting::where('company_id', $request->query('company_id'))->first();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $setting]);
    }

    public function update(UpdateCompanyTaxSettingRequest $request)
    {
        $setting = CompanyTaxSetting::updateOrCreate(
            ['company_id' => $request->validated('company_id')],
            $request->validated(),
        );

        return response()->json(['success' => true, 'message' => 'Pengaturan pajak company berhasil disimpan', 'data' => $setting]);
    }
}