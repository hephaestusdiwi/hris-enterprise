<?php

namespace App\Modules\OvertimeRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date'],
            'planned_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}