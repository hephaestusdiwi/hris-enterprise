<?php

namespace App\Modules\Payroll\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyBankSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:255'],
        ];
    }
}
