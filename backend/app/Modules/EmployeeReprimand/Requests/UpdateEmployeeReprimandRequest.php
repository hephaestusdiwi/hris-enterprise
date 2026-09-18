<?php

namespace App\Modules\EmployeeReprimand\Requests;

use App\Modules\EmployeeReprimand\Models\EmployeeReprimand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeReprimandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reprimand_type' => ['sometimes', Rule::in(EmployeeReprimand::TYPES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'reason' => ['sometimes', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }
}