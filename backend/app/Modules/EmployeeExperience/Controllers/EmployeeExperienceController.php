<?php
 
namespace App\Modules\EmployeeExperience\Controllers;
 
use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeExperience\Models\EmployeeExperience;
use App\Modules\EmployeeExperience\Requests\StoreEmployeeExperienceRequest;
use App\Modules\EmployeeExperience\Requests\UpdateEmployeeExperienceRequest;
use Illuminate\Http\Request;
 
/**
 * Employee Experience (General > Education & Experience tab). Sama seperti
 * EmployeeEducation -- self-service full CRUD, tanpa attachment (tidak
 * diminta di scope).
 */
class EmployeeExperienceController extends Controller
{
    // ---------- Self-service ----------
    public function indexMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->experiences()->orderByDesc('start_date')->get(),
        ]);
    }

    public function storeMine(StoreEmployeeExperienceRequest $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');

        $experience = $employee->experiences()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pengalaman kerja berhasil ditambahkan',
            'data' => $experience,
        ], 201);
    }

    public function updateMine(UpdateEmployeeExperienceRequest $request, EmployeeExperience $experience)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');
        abort_unless($experience->employee_id === $employee->id, 403);

        $experience->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pengalaman kerja berhasil diperbarui',
            'data' => $experience->fresh(),
        ]);
    }

    public function destroyMine(Request $request, EmployeeExperience $experience)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');
        abort_unless($experience->employee_id === $employee->id, 403);

        $experience->delete();

        return response()->json(['success' => true, 'message' => 'Riwayat pengalaman kerja berhasil dihapus', 'data' => null]);
    }

    // ---------- Admin ----------
    public function indexForEmployee(Employee $employee)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->experiences()->orderByDesc('start_date')->get(),
        ]);
    }

    public function storeForEmployee(StoreEmployeeExperienceRequest $request, Employee $employee)
    {
        $experience = $employee->experiences()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pengalaman kerja berhasil ditambahkan',
            'data' => $experience,
        ], 201);
    }

    public function updateForEmployee(UpdateEmployeeExperienceRequest $request, Employee $employee, EmployeeExperiece $experience)
    {
        abort_unless($experience->employee_id === $employee->id, 404);

        $experience->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pengalaman kerja berhasil diperbarui',
            'data' => $experience->fresh(),
        ]);
    }

    public function destroyForEmployee(Employee $employee, EmployeeExperience $experience)
    {
        abort_unless($experience->employee_id === $employee->id, 404);

        $experience->delete();

        return response()->json(['success' => true, 'message' => 'Riwayat pengalaman kerja berhasil dihapus', 'data' => null]);
    }
}