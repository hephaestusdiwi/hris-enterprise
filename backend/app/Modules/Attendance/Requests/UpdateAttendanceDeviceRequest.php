<?php

namespace App\Modules\Attendance\Requests;

use App\Modules\Attendance\Enums\AttendanceDeviceType;
use App\Modules\Branch\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => [
                'nullable',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    if ($value && ! Branch::where('id', $value)
                        ->where('company_id', $this->input('company_id'))
                        ->exists()) {
                        $fail('Branch tidak termasuk dalam company yang dipilih.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AttendanceDeviceType::class)],
            'is_active' => ['boolean'],
        ];
    }
}