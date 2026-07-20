<?php

namespace App\Modules\WorkingSchedule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkingScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('working_schedules', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('working_schedule')),
            ],
            'is_active' => ['boolean'],
            'details' => ['required', 'array', 'size:7'],
            'details.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'details.*.shift_id' => ['nullable', 'exists:shifts,id'],
        ];
    }
}