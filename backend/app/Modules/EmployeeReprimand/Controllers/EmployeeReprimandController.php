<?php

namespace App\Modules\EmployeeReprimand\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeReprimand\Exceptions\EmployeeReprimandException;
use App\Modules\EmployeeReprimand\Models\EmployeeReprimand;
use App\Modules\EmployeeReprimand\Requests\StoreEmployeeReprimandRequest;
use App\Modules\EmployeeReprimand\Requests\UpdateEmployeeReprimandRequest;
use App\Modules\EmployeeReprimand\Requests\VoidEmployeeReprimandRequest;
use App\Modules\EmployeeReprimand\Services\EmployeeReprimandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Employee Reprimand (History > Reprimand ala Mekari Talenta).
 *
 * Filosofi sama seperti EmployeeDeduction: record disipliner TIDAK PERNAH
 * di-hard-delete. "Hapus" = void (lewat Service::void(), butuh reason,
 * tercatat siapa & kapan). Self-service HANYA read-only (sama seperti
 * /my-assets) -- employee wajar lihat riwayat SP miliknya sendiri, tapi
 * jelas tidak boleh create/edit/void record disipliner sendiri.
 */
class EmployeeReprimandController extends Controller
{
    public function __construct(private EmployeeReprimandService $service) {}

    // ---------- Self-service (read-only) ----------

    public function indexMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->reprimands()->latest('date')->get(),
        ]);
    }

    // ---------- Admin ----------

    private function applyFilters(Request $request, Employee $employee)
    {
        return $employee->reprimands()
            ->with(['createdBy', 'voidedBy'])
            ->when($request->query('reprimand_type'), fn ($q, $v) => $q->where('reprimand_type', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('date_from'), fn ($q, $v) => $q->whereDate('date', '>=', $v))
            ->when($request->query('date_to'), fn ($q, $v) => $q->whereDate('date', '<=', $v));
    }

    public function indexForEmployee(Request $request, Employee $employee)
    {
        $reprimands = $this->applyFilters($request, $employee)->latest('date')->paginate(20);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $reprimands]);
    }

    public function showForEmployee(Employee $employee, EmployeeReprimand $reprimand)
    {
        abort_unless($reprimand->employee_id === $employee->id, 404);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $reprimand->load(['createdBy', 'voidedBy']),
        ]);
    }

    public function storeForEmployee(StoreEmployeeReprimandRequest $request, Employee $employee)
    {
        $data = $this->withAttachment($request);
        $data['employee_id'] = $employee->id;

        $reprimand = $this->service->create($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reprimand berhasil dibuat',
            'data' => $reprimand,
        ], 201);
    }

    public function updateForEmployee(UpdateEmployeeReprimandRequest $request, Employee $employee, EmployeeReprimand $reprimand)
    {
        abort_unless($reprimand->employee_id === $employee->id, 404);

        try {
            $reprimand = $this->service->update($reprimand, $this->withAttachment($request, $reprimand));

            return response()->json(['success' => true, 'message' => 'Reprimand berhasil diperbarui', 'data' => $reprimand]);
        } catch (EmployeeReprimandException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    public function voidForEmployee(VoidEmployeeReprimandRequest $request, Employee $employee, EmployeeReprimand $reprimand)
    {
        abort_unless($reprimand->employee_id === $employee->id, 404);

        try {
            $reprimand = $this->service->void($reprimand, $request->validated('reason'), $request->user());

            return response()->json(['success' => true, 'message' => 'Reprimand berhasil di-void', 'data' => $reprimand]);
        } catch (EmployeeReprimandException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 422);
        }
    }

    // ---------- Shared ----------

    private function withAttachment(Request $request, ?EmployeeReprimand $existing = null): array
    {
        $data = $request->validated();
        unset($data['attachment']);

        if ($request->hasFile('attachment')) {
            if ($existing && $existing->attachment_path && Storage::disk('public')->exists($existing->attachment_path)) {
                Storage::disk('public')->delete($existing->attachment_path);
            }
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('employees/reprimands', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
        }

        return $data;
    }
}