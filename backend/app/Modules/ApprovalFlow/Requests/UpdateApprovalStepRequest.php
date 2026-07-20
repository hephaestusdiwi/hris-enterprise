<?php

namespace App\Modules\ApprovalFlow\Requests;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sequence' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('approval_steps', 'sequence')
                    ->where('approval_flow_id', $this->route('approvalFlow')->id)
                    ->ignore($this->route('step')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'approver_type' => ['required', Rule::enum(ApproverType::class)],
            'approver_employee_id' => [
                Rule::requiredIf($this->input('approver_type') === ApproverType::SpecificEmployee->value),
                Rule::prohibitedIf($this->input('approver_type') !== ApproverType::SpecificEmployee->value),
                'nullable',
                'exists:employees,id',
            ],
            'approver_role_id' => [
                Rule::requiredIf($this->input('approver_type') === ApproverType::SpecificRole->value),
                Rule::prohibitedIf($this->input('approver_type') !== ApproverType::SpecificRole->value),
                'nullable',
                'exists:roles,id',
            ],
            'is_active' => ['boolean'],
        ];
    }
}