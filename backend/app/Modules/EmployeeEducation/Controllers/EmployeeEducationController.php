<?php

namespace App\Modules\EmployeeEducation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeEducation\Models\EmployeeEducation;
use App\Modules\EmployeeEducation\Requests\StoreEmployeeEducationRequest;
use App\Modules\EmployeeEducation\Requests\UpdateEmployeeEducationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Employee Education (General > Education & Experience tab ala Mekari
 * Talenta). Data riwayat pendidikan milik employee sendiri -- self-service
 * FULL CRUD (create/edit/delete), sama seperti /my-documents, BUKAN
 * read-only seperti /my-assets, karena ini data biografis yang wajar
 * di-maintain sendiri oleh employee, bukan sesuatu yang HR "assign".
 */
class EmployeeEducationController extends Controller
{
    // ---------- Self-service ----------

    public function indexMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->educations()->orderByDesc('start_date')->get(),
        ]);
    }

    public function storeMine(StoreEmployeeEducationRequest $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        $education = $employee->educations()->create($this->withAttachment($request));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pendidikan berhasil ditambahkan',
            'data' => $education,
        ], 201);
    }

    public function updateMine(UpdateEmployeeEducationRequest $request, EmployeeEducation $education)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');
        abort_unless($education->employee_id === $employee->id, 403);

        $education->update($this->withAttachment($request, $education));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pendidikan berhasil diperbarui',
            'data' => $education->fresh(),
        ]);
    }

    public function destroyMine(Request $request, EmployeeEducation $education)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');
        abort_unless($education->employee_id === $employee->id, 403);

        $this->deleteAttachment($education);
        $education->delete();

        return response()->json(['success' => true, 'message' => 'Riwayat pendidikan berhasil dihapus', 'data' => null]);
    }

    // ---------- Admin ----------

    public function indexForEmployee(Employee $employee)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->educations()->orderByDesc('start_date')->get(),
        ]);
    }

    public function storeForEmployee(StoreEmployeeEducationRequest $request, Employee $employee)
    {
        $education = $employee->educations()->create($this->withAttachment($request));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pendidikan berhasil ditambahkan',
            'data' => $education,
        ], 201);
    }

    public function updateForEmployee(UpdateEmployeeEducationRequest $request, Employee $employee, EmployeeEducation $education)
    {
        abort_unless($education->employee_id === $employee->id, 404);

        $education->update($this->withAttachment($request, $education));

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pendidikan berhasil diperbarui',
            'data' => $education->fresh(),
        ]);
    }

    public function destroyForEmployee(Employee $employee, EmployeeEducation $education)
    {
        abort_unless($education->employee_id === $employee->id, 404);

        $this->deleteAttachment($education);
        $education->delete();

        return response()->json(['success' => true, 'message' => 'Riwayat pendidikan berhasil dihapus', 'data' => null]);
    }

    // ---------- Shared ----------

    private function withAttachment(Request $request, ?EmployeeEducation $existing = null): array
    {
        $data = $request->validated();
        unset($data['attachment']);

        if ($request->hasFile('attachment')) {
            if ($existing) {
                $this->deleteAttachment($existing);
            }
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('employees/educations', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        return $data;
    }

    private function deleteAttachment(EmployeeEducation $education): void
    {
        if ($education->attachment_path && Storage::disk('public')->exists($education->attachment_path)) {
            Storage::disk('public')->delete($education->attachment_path);
        }
    }
}