<?php

namespace App\Modules\Shift\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
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
                Rule::unique('shifts', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('shift')),
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_overnight' => ['boolean'],
            'break_start_time' => ['nullable', 'date_format:H:i'],
            'break_end_time' => ['nullable', 'date_format:H:i', 'after:break_start_time'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0'],
            'check_in_before_minutes' => ['required', 'integer', 'min:0'],
            'check_out_after_minutes' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}