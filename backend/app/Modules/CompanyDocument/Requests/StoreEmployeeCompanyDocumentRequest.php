<?php

namespace App\Modules\CompanyDocument\Requests;

use App\Modules\CompanyDocument\Models\CompanyDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Buat dokumen terikat 1 employee -- endpoint
 * /employees/{employee}/company-documents. Kategori boleh APA AJA
 * (public-with-context maupun private) -- authorization dinamis per
 * kategori dicek di Controller (CompanyDocument::managePermissionFor),
 * bukan lewat route middleware statis, karena satu endpoint ini
 * menangani kategori dengan permission manage yang beda-beda.
 */
class StoreEmployeeCompanyDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(CompanyDocument::CATEGORIES))],
            'module_context' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'document' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
        ];
    }
}