<?php

namespace App\Modules\CompanyObligation\Requests;

use App\Modules\CompanyObligation\Models\CompanyObligation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorization sesungguhnya dicek lewat CompanyObligationPolicy::create()
 * di Controller ($this->authorize()) -- pola FormRequest di project ini
 * yang authorize()-nya selalu true, bukan menaruh RBAC di sini.
 */
class StoreCompanyObligationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'type' => ['required', Rule::in(array_keys(CompanyObligation::TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pic_employee_id' => ['nullable', 'exists:employees,id'],
            'due_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}