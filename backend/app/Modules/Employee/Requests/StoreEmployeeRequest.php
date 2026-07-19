<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Employment Information
            'employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number'],
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'job_level_id' => ['nullable', 'exists:job_levels,id'],
            'employment_status_id' => ['nullable', 'exists:employment_statuses,id'],
            'manager_employee_id' => ['nullable', 'exists:employees,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'join_date' => ['required', 'date'],
            'resign_date' => ['nullable', 'date', 'after_or_equal:join_date'],

            // Personal Information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],

            // Contact Information
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],

            // Identity Information
            'national_id_number' => ['nullable', 'string', 'max:50', 'unique:employees,national_id_number'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_holder_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}