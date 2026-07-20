<?php

namespace App\Modules\ApprovalFlow\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalFlowAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'exists:employees,id',
                Rule::unique('approval_flow_assignments', 'employee_id'),
            ],
            'is_active' => ['boolean'],
        ];
    }
}