<?php

namespace App\Modules\CompanyDocument\Requests;

use App\Modules\CompanyDocument\Models\CompanyDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Buat dokumen company-wide (employee_id null) -- endpoint /company-documents.
 * Kategori DIBATASI ke PUBLIC_CATEGORIES aja di sini; kategori private
 * (KTP dkk) cuma bisa lewat StoreEmployeeCompanyDocumentRequest karena
 * wajib terikat employee.
 */
class StoreCompanyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(CompanyDocument::PUBLIC_CATEGORIES)],
            'module_context' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'document' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
        ];
    }
}