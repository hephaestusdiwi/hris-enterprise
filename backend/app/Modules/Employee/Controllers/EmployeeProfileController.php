<?php
 
namespace App\Modules\Employee\Controllers;
 
use App\Http\Controllers\Controller;
use App\Modules\Employee\Requests\UpdateOwnProfileRequest;
use Illuminate\Http\Request;
 
/**
 * Employee Profile (self-service) -- "General" tab ala Mekari Talenta
 * Employee Profile: Personal (self-editable, subset terbatas) + Employment
 * (read-only, sumbernya tetap Employee Movement).
 *
 * Pola sama seperti AttendanceSelfServiceController::myAttendances() --
 * tanpa permission khusus, object-level check "employee ini = employee
 * milik user yang login", BUKAN policy EmployeePolicy::view()/update()
 * yang memang sengaja scoped ke HR/admin/manager-subordinate (lihat
 * komentar di EmployeePolicy). Self-service profile itu domain terpisah:
 * setiap employee wajib bisa lihat & edit sebagian data dirinya sendiri
 * terlepas dari permission RBAC apa pun yang dia punya.
 */
class EmployeeProfileController extends Controller
{
    /**
     * Sama seperti EmployeeController::$relations, supaya bentuk response
     * (nested company/branch/dst) konsisten dengan endpoint admin.
     */
    protected array $relations = ['company', 'branch', 'department', 'position', 'jobLevel', 'workingSchedule', 'employmentStatus', 'employmentType', 'manager'];

    public function show(Request $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung data employee');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->load($this->relations),
        ]);
    }

    public function update(UpdateOwnProfileRequest $request)
    {
        $employee = $request->user()->employee;

        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');

        $employee->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile berhasil diperbarui',
            'data' => $employee->load($this->relations),
        ]);
    }
}