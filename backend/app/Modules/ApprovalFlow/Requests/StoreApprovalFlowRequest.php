<?php

namespace App\Modules\ApprovalFlow\Requests;

use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\Branch\Models\Branch;
use App\Modules\Department\Models\Department;
use App\Modules\JobLevel\Models\JobLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreApprovalFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'exists:companies,id',
            ],

            'approval_type' => [
                'required',
                'string',
                'max:50',
                Rule::in([
                    'hiring_requisition',
                    'leave',
                    'attendance',
                    'attendance_request',
                    'reimbursement',
                    'loan',
                    'cash_advance',
                    'cash_advance_settlement',
                    'payroll',
                    'employee_movement',
                ]),
            ],

            'branch_id' => [
                'nullable',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    if (
                        $value &&
                        ! Branch::where('id', $value)
                            ->where('company_id', $this->input('company_id'))
                            ->exists()
                    ) {
                        $fail(
                            'Branch tidak termasuk dalam company yang dipilih.'
                        );
                    }
                },
            ],

            'department_id' => [
                'nullable',
                'exists:departments,id',
                function ($attribute, $value, $fail) {
                    if (
                        $value &&
                        ! Department::where('id', $value)
                            ->where('company_id', $this->input('company_id'))
                            ->exists()
                    ) {
                        $fail(
                            'Department tidak termasuk dalam company yang dipilih.'
                        );
                    }
                },
            ],

            'job_level_id' => [
                'nullable',
                'exists:job_levels,id',
                function ($attribute, $value, $fail) {
                    if (
                        $value &&
                        ! JobLevel::where('id', $value)
                            ->where('company_id', $this->input('company_id'))
                            ->exists()
                    ) {
                        $fail(
                            'Job Level tidak termasuk dalam company yang dipilih.'
                        );
                    }
                },
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('approval_flows', 'code')
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'company_id',
                                $this->input('company_id')
                            )
                            ->whereNull('deleted_at')
                    ),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $filledScopes = collect([
                'branch_id',
                'department_id',
                'job_level_id',
            ])->filter(
                fn ($field) => $this->filled($field)
            );

            /*
             * Satu Approval Flow hanya boleh memiliki
             * satu dimensi scope.
             */
            if ($filledScopes->count() > 1) {
                $validator->errors()->add(
                    'branch_id',
                    'Hanya boleh isi salah satu scope: Branch, Department, atau Job Level.'
                );

                return;
            }

            /*
             * Company-wide:
             *
             * Satu company boleh memiliki banyak Approval Flow,
             * tetapi hanya satu untuk setiap approval_type.
             *
             * Contoh:
             *   Company 1 + hiring_requisition → boleh
             *   Company 1 + leave              → boleh
             *   Company 1 + reimbursement      → boleh
             *   Company 1 + cash_advance       → boleh
             *
             * Tetapi:
             *   Company 1 + leave → dua kali = tidak boleh.
             */
            if ($filledScopes->isEmpty()) {
                $exists = ApprovalFlow::query()
                    ->where(
                        'company_id',
                        $this->input('company_id')
                    )
                    ->where(
                        'approval_type',
                        $this->input('approval_type')
                    )
                    ->whereNull('branch_id')
                    ->whereNull('department_id')
                    ->whereNull('job_level_id')
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'approval_type',
                        'Approval Flow untuk jenis approval ini pada scope Company-wide sudah ada.'
                    );
                }
            }
        });
    }
}