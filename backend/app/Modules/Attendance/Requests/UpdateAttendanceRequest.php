<?php

namespace App\Modules\Attendance\Requests;

use App\Modules\Attendance\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => [
                'required',
                'exists:employees,id',
                Rule::unique('attendances', 'employee_id')
                    ->where('attendance_date', $this->input('attendance_date'))
                    ->ignore($this->route('attendance')),
            ],
            'attendance_date' => ['required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date', 'after_or_equal:clock_in'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.unique' => 'Employee ini sudah punya record attendance di tanggal tersebut.',
        ];
    }
}