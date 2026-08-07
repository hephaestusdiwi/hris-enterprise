<?php

namespace App\Modules\Bpjs\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bpjs\Models\EmployeeBpjsParticipation;
use App\Modules\Bpjs\Requests\UpdateEmployeeBpjsParticipationRequest;
use App\Modules\Employee\Models\Employee;

class EmployeeBpjsParticipationController extends Controller
{
    public function index()
    {
        $participations = EmployeeBpjsParticipation::with('employee')->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $participations]);
    }

    public function show(Employee $employee)
    {
        $participation = EmployeeBpjsParticipation::where('employee_id', $employee->id)->first();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $participation]);
    }

    public function update(UpdateEmployeeBpjsParticipationRequest $request, Employee $employee)
    {
        $participation = EmployeeBpjsParticipation::updateOrCreate(
            ['employee_id' => $employee->id],
            $request->validated(),
        );

        return response()->json(['success' => true, 'message' => 'Kepesertaan BPJS berhasil disimpan', 'data' => $participation]);
    }
}