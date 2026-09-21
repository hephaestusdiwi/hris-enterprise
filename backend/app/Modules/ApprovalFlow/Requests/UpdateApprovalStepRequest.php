<?php

namespace App\Modules\ApprovalFlow\Requests;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /** Lihat StoreApprovalStepRequest::withValidator() — guard yang sama. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $approvalFlow = $this->route('approvalFlow');

            if (
                $approvalFlow
                && $approvalFlow->approval_type === 'payroll'
                && $this->input('approver_type') === ApproverType::DirectManager->value
            ) {
                $validator->errors()->add(
                    'approver_type',
                    'Approver type Direct Manager tidak bisa dipakai untuk Approval Flow Payroll — Payroll Run tidak punya employee subject, approval-nya tidak akan pernah bisa diputuskan siapa pun. Gunakan Specific Employee atau Specific Role.'
                );
            }
        });
    }
}