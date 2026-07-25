<?php

namespace App\Modules\LeaveType\Requests;

use App\Modules\LeaveType\Enums\GenderRestriction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('leave_types', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('leaveType')),
            ],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_paid' => ['boolean'],
            'max_days_per_year' => ['nullable', 'integer', 'min:0'],
            'min_service_months' => ['integer', 'min:0'],
            'requires_attachment' => ['boolean'],
            'gender_restriction' => ['nullable', Rule::enum(GenderRestriction::class)],
            'carry_over_allowed' => ['boolean'],
            'carry_over_max_days' => ['nullable', 'integer', 'min:0'],
            'carry_over_expiry_month' => ['nullable', 'integer', 'between:1,12'],
            'requires_approval' => ['boolean'],
            'allow_half_day' => ['boolean'],
            'allow_hourly' => ['boolean'],
            'requires_balance' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}