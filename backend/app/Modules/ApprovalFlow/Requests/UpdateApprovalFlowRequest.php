<?php

namespace App\Modules\ApprovalFlow\Requests;

use App\Modules\Branch\Models\Branch;
use App\Modules\Department\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApprovalFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => [
                'nullable',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    if ($value && ! Branch::where('id', $value)
                        ->where('company_id', $this->input('company_id'))
                        ->exists()) {
                        $fail('Branch tidak termasuk dalam company yang dipilih.');
                    }
                },
            ],
            'department_id' => [
                'nullable',
                'exists:departments,id',
                function ($attribute, $value, $fail) {
                    if ($value && ! Department::where('id', $value)
                        ->where('company_id', $this->input('company_id'))
                        ->exists()) {
                        $fail('Department tidak termasuk dalam company yang dipilih.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('approval_flows', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('approval_flow')),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}