<?php

namespace App\Modules\CompanyObligation\Requests;

use App\Modules\CompanyObligation\Enums\CompanyObligationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `type` SENGAJA TIDAK termasuk field yang bisa diubah -- kalau kategori
 * kebutuhannya beda (mis. dari Sewa Ruko jadi MOU Legal), itu harusnya
 * record baru, bukan edit record lama (pola sama dengan
 * CompanyDocument::category yang immutable setelah create).
 */
class UpdateCompanyObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'required', 'exists:companies,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pic_employee_id' => ['nullable', 'exists:employees,id'],
            'due_date' => ['sometimes', 'required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', Rule::enum(CompanyObligationStatus::class)],
        ];
    }
}