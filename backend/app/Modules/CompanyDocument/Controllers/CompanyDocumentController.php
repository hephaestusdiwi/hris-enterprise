<?php

namespace App\Modules\CompanyDocument\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\CompanyDocument\Models\CompanyDocument;
use App\Modules\CompanyDocument\Requests\StoreCompanyDocumentRequest;
use App\Modules\CompanyDocument\Requests\StoreEmployeeCompanyDocumentRequest;
use App\Modules\CompanyDocument\Requests\UpdateCompanyDocumentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * CompanyDocument -- 1 module generic buat Open/Public Document (sop,
 * handbook, work_guide, form_template) dan Hidden/Private Document (ktp,
 * bank_account, employment_contract, disciplinary). Lihat
 * CompanyDocument::CATEGORIES untuk pemetaan visibility & permission.
 *
 * PRINSIP KRUSIAL: kategori (dan karena itu visibility + permission) di
 * SET SEKALI pas create dan TIDAK PERNAH bisa diubah lewat update --
 * lihat komentar di UpdateCompanyDocumentRequest.
 */
class CompanyDocumentController extends Controller
{
    // ---------- Self-service (read-only) ----------

    public function indexMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->companyDocuments()->latest()->get(),
        ]);
    }

    // ---------- Company-wide (public, employee_id null) ----------

    public function index(Request $request)
    {
        $documents = CompanyDocument::query()
            ->whereNull('employee_id')
            ->when($request->query('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->query('module_context'), fn ($q, $v) => $q->where('module_context', $v))
            ->latest()
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $documents]);
    }

    public function store(StoreCompanyDocumentRequest $request)
    {
        $document = $this->createDocument($request, null);

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil diupload', 'data' => $document], 201);
    }

    public function update(UpdateCompanyDocumentRequest $request, CompanyDocument $document)
    {
        abort_if($document->employee_id !== null, 404);
        $this->authorizeManage($request, $document->category, 'edit');

        $document->update($this->attachmentFields($request, $document));

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil diperbarui', 'data' => $document->fresh()]);
    }

    public function destroy(Request $request, CompanyDocument $document)
    {
        abort_if($document->employee_id !== null, 404);
        $this->authorizeManage($request, $document->category, 'delete');

        $this->deleteFile($document);
        $document->delete();

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus', 'data' => null]);
    }

    // ---------- Employee-scoped (admin, public-with-context ATAU private) ----------

    public function indexForEmployee(Request $request, Employee $employee)
    {
        $visibleCategories = collect(CompanyDocument::CATEGORIES)
            ->filter(fn ($meta) => $request->user()->can($meta['view_permission']))
            ->keys();

        $documents = $employee->companyDocuments()
            ->whereIn('category', $visibleCategories)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $documents]);
    }

    public function storeForEmployee(StoreEmployeeCompanyDocumentRequest $request, Employee $employee)
    {
        $this->authorizeManage($request, $request->validated('category'), 'create');

        $document = $this->createDocument($request, $employee->id);

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil diupload', 'data' => $document], 201);
    }

    public function updateForEmployee(UpdateCompanyDocumentRequest $request, Employee $employee, CompanyDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 404);
        $this->authorizeManage($request, $document->category, 'edit');

        $document->update($this->attachmentFields($request, $document));

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil diperbarui', 'data' => $document->fresh()]);
    }

    public function destroyForEmployee(Request $request, Employee $employee, CompanyDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 404);
        $this->authorizeManage($request, $document->category, 'delete');

        $this->deleteFile($document);
        $document->delete();

        return response()->json(['success' => true, 'message' => 'Dokumen berhasil dihapus', 'data' => null]);
    }

    // ---------- Shared ----------

    private function authorizeManage(Request $request, string $category, string $action): void
    {
        abort_unless(
            $request->user()->can(CompanyDocument::managePermissionFor($category, $action)),
            403
        );
    }

    private function createDocument(Request $request, ?int $employeeId): CompanyDocument
    {
        $category = $request->validated('category');
        $file = $request->file('document');
        $path = $file->store('company-documents', 'public');

        return CompanyDocument::create([
            'employee_id' => $employeeId,
            'category' => $category,
            'visibility' => CompanyDocument::visibilityFor($category),
            'module_context' => $request->validated('module_context'),
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by_user_id' => $request->user()->id,
        ]);
    }

    private function attachmentFields(Request $request, CompanyDocument $existing): array
    {
        $data = $request->validated();
        unset($data['document']);

        if ($request->hasFile('document')) {
            $this->deleteFile($existing);
            $file = $request->file('document');
            $data['file_path'] = $file->store('company-documents', 'public');
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
            $data['mime_type'] = $file->getMimeType();
        }

        return $data;
    }

    private function deleteFile(CompanyDocument $document): void
    {
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }
    }
}