<?php

namespace App\Modules\ApprovalFlow\Requests;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApprovalStepRequest extends FormRequest
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
                    ->where('approval_flow_id', $this->route('approvalFlow')->id),
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

    /**
     * PayrollRun bukan Employee — PayrollApprovalService selalu resolve
     * approver dengan subject employee NULL (lihat PayrollApprovalService),
     * jadi approver_type=DirectManager di flow approval_type=payroll PASTI
     * balik array approver kosong dan approval-nya nyangkut pending selamanya
     * tanpa ada yang bisa mutusin. Dicegah di sini SEBELUM tersimpan, bukan
     * cuma didokumentasikan sebagai "sinyal salah konfigurasi".
     */
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