<?php

namespace App\Modules\ApprovalFlow\Requests;

use App\Modules\Branch\Models\Branch;
use App\Modules\Department\Models\Department;
use App\Modules\JobLevel\Models\JobLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'job_level_id' => [
                'nullable',
                'exists:job_levels,id',
                function ($attribute, $value, $fail) {
                    if ($value && ! JobLevel::where('id', $value)
                        ->where('company_id', $this->input('company_id'))
                        ->exists()) {
                        $fail('Job Level tidak termasuk dalam company yang dipilih.');
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $filledScopes = collect(['branch_id', 'department_id', 'job_level_id'])
                ->filter(fn ($field) => $this->filled($field));

            if ($filledScopes->count() > 1) {
                $validator->errors()->add(
                    'branch_id',
                    'Hanya boleh isi salah satu scope: Branch, Department, ATAU Job Level (tidak boleh kombinasi). Kosongkan semua untuk scope Company-wide.'
                );
            }
        });
    }
}
