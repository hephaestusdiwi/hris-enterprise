<?php

namespace App\Modules\AttendanceRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendance_date' => ['required', 'date'],
            // Minimal salah satu harus diisi (checkbox pattern ala Talenta:
            // clock-in saja, clock-out saja, atau dua-duanya).
            'requested_clock_in' => ['nullable', 'required_without:requested_clock_out', 'date'],
            'requested_clock_out' => ['nullable', 'required_without:requested_clock_in', 'date', 'after_or_equal:requested_clock_in'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120', 'mimes:jpg,jpeg,pdf,csv,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'requested_clock_in.required_without' => 'Isi salah satu: requested clock-in atau requested clock-out.',
            'requested_clock_out.required_without' => 'Isi salah satu: requested clock-in atau requested clock-out.',
            'attachments.max' => 'Maksimal 5 file attachment per request.',
            'attachments.*.max' => 'Ukuran tiap file maksimal 5MB.',
            'attachments.*.mimes' => 'Format file harus JPG, JPEG, PDF, CSV, atau XLSX.',
        ];
    }
}