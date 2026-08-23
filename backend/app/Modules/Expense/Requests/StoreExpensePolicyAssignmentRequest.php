<?php

namespace App\Modules\Expense\Requests;

use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Models\ExpensePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpensePolicyAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'expense_policy_id' => ['required', 'exists:expense_policies,id'],
            'effective_date' => [
                'required',
                'date',
                // Cegah 2 assignment mulai di tanggal yang sama untuk
                // employee yang sama -- cermin unique(employee_id,
                // effective_date) di migration, supaya errornya rapi
                // (422 tervalidasi) bukan DB exception mentah.
                Rule::unique('expense_policy_assignments', 'effective_date')
                    ->where('employee_id', $this->input('employee_id')),
            ],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $employee = Employee::find($this->input('employee_id'));
            $policy = ExpensePolicy::find($this->input('expense_policy_id'));

            if ($employee && $policy && $employee->company_id !== $policy->company_id) {
                $validator->errors()->add(
                    'expense_policy_id',
                    'Policy harus berasal dari company yang sama dengan employee.',
                );
            }
        });
    }
}