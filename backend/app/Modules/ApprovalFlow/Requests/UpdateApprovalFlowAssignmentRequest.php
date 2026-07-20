<?php

namespace App\Modules\ApprovalFlow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalFlowAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_flow_id' => ['required', 'exists:approval_flows,id'],
            'employee_id' => [
                'required',
                'exists:employees,id',
                Rule::unique('approval_flow_assignments', 'employee_id')
                    ->ignore($this->route('assignment')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}