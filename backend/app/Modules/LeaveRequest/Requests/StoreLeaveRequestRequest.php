<?php

namespace App\Modules\LeaveRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['boolean'],
            'half_day_session' => ['nullable', 'in:morning,afternoon', 'required_if:is_half_day,true'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string'],
            'attachment_path' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $isHalfDay = $this->boolean('is_half_day');
            $isHourly = $this->filled('start_time') && $this->filled('end_time');

            if ($isHalfDay && $isHourly) {
                $validator->errors()->add('is_half_day', 'Tidak bisa mengajukan setengah hari dan per jam sekaligus.');
            }

            if (($isHalfDay || $isHourly) && $this->input('start_date') !== $this->input('end_date')) {
                $validator->errors()->add('end_date', 'Pengajuan setengah hari/per jam hanya untuk 1 tanggal yang sama.');
            }
        });
    }
}