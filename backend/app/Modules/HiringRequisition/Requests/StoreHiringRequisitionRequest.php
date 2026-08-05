<?php

namespace App\Modules\HiringRequisition\Requests;

use App\Modules\HiringRequisition\Models\HiringRequisition;
use Illuminate\Foundation\Http\FormRequest;

class StoreHiringRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', HiringRequisition::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'reason' => ['required', 'string', 'in:new_position,replacement'],
            'replacement_for_employee_id' => ['nullable', 'integer', 'exists:employees,id', 'required_if:reason,replacement'],
            'employment_type' => ['required', 'string'],
            'headcount_requested' => ['required', 'integer', 'min:1'],
            'target_start_date' => ['nullable', 'date'],
            'justification' => ['required', 'string'],
        ];
    }
}