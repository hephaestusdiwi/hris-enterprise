<?php

namespace App\Modules\ContractProbationSetting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContractProbationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit contract probation settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'contract_reminder_days' => ['required', 'integer', 'min:1', 'max:365'],
            'probation_reminder_days' => ['required', 'integer', 'min:1', 'max:365'],
            'email_reminder_enabled' => ['required', 'boolean'],
            'manager_reminder_enabled' => ['required', 'boolean'],
        ];
    }
}
