<?php

namespace App\Modules\Bpjs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bpjs\Models\CompanyBpjsSetting;
use App\Modules\Bpjs\Requests\UpdateCompanyBpjsSettingRequest;
use Illuminate\Http\Request;

class CompanyBpjsSettingController extends Controller
{
    public function show(Request $request)
    {
        $companyId = $request->query('company_id');
        $setting = CompanyBpjsSetting::where('company_id', $companyId)->first();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $setting]);
    }

    public function update(UpdateCompanyBpjsSettingRequest $request)
    {
        $setting = CompanyBpjsSetting::updateOrCreate(
            ['company_id' => $request->validated('company_id')],
            $request->validated(),
        );

        return response()->json(['success' => true, 'message' => 'Pengaturan default BPJS berhasil disimpan', 'data' => $setting]);
    }
}