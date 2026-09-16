<?php
 
namespace App\Modules\EmployeeDocument\Controllers;
 
use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeDocument\Models\EmployeeDocument;
use App\Modules\EmployeeDocument\Requests\StoreEmployeeDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
 
/**
 * Employee Documents (Files tab ala Mekari Talenta).
 *
 * Self-service (/my-documents) TANPA permission gate -- pola sama seperti
 * /my-profile: employee boleh upload/lihat/hapus dokumen dia sendiri
 * (KTP, NPWP, dst) terlepas dari permission RBAC apa pun yang dia punya.
 *
 * Admin (/employees/{employee}/documents) pakai permission baru
 * 'view employee documents' / 'create employee documents' /
 * 'delete employee documents' -- konsisten pola EmployeeAllowance dkk
 * (permission per-resource, bukan reuse 'view employees').
 */
class EmployeeDocumentController extends Controller
{
    // ---------- Self-service ----------

    public function indexMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->documents()->latest()->get(),
        ]);
    }

    public function storeMine(StoreEmployeeDocumentRequest $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');

        $document = $this->storeDocument($employee, $request);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diupload',
            'data' => $document,
        ], 201);
    }

    public function destroyMine(Request $request, EmployeeDocument $document)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee');
        abort_unless($document->employee_id === $employee->id, 403);

        $this->deleteDocument($document);

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus', 'data' => null]);
    }

    // ---------- Admin ----------
    public function indexForEmployee(Employee $employee)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->documents()->latest()->get(),
        ]);
    }

    public function storeForEmployee(StoreEmployeeDocumentRequest $request, Employee $employee)
    {
        $document = $this->storeDocument($employee, $request);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil di upload',
            'data' => $document,
        ], 201);
    }

    public function destroyForEmployee(Employee $employee, EmployeeDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 404);

        $this->deleteDocument($document);

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus', 'data' => null]);
    }

    // ---------- Shared ----------
    private function storeDocument(Employee $employee, StoreEmployeeDocumentRequest $request): EmployeeDocument
    {
        $file = $request->file('document');
        $path = $file->store('employees/'.$employee->id.'/documents', 'public');

        return $employee->document()->create([
            'category' => $request->validated('category'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by_user_id' => $request->user()->id,
            'notes' => $request->validated('notes'),
        ]);
    }

    private function deleteDocument(EmployeeDocument $document): void
    {
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();
    }
}