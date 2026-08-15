<?php

namespace App\Modules\ContractProbationSetting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ContractProbationSetting\Models\ContractProbationSetting;
use App\Modules\ContractProbationSetting\Requests\UpdateContractProbationSettingRequest;

class ContractProbationSettingController extends Controller
{
    public function show()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ContractProbationSetting::current(),
        ]);
    }

    public function update(UpdateContractProbationSettingRequest $request)
    {
        $setting = ContractProbationSetting::query()->first();

        if ($setting) {
            $setting->update($request->validated());
        } else {
            $setting = ContractProbationSetting::create($request->validated());
        }

        return response()->json([
            'success' => true,
            'message' => 'Setting berhasil disimpan.',
            'data' => $setting,
        ]);
    }
}
