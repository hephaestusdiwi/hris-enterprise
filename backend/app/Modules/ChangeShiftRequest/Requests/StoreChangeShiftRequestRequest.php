<?php

namespace App\Modules\ChangeShiftRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChangeShiftRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date'],
            'requested_shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}