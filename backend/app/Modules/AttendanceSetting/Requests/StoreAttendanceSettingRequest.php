<?php

namespace App\Modules\AttendanceSetting\Requests;

use App\Modules\AttendanceSetting\Models\AttendanceSetting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'require_photo' => ['boolean'],
            'require_location' => ['boolean'],
            'office_latitude' => ['nullable', 'required_if:require_location,true', 'numeric', 'between:-90,90'],
            'office_longitude' => ['nullable', 'required_if:require_location,true', 'numeric', 'between:-180,180'],
            'location_radius_meters' => ['required', 'integer', 'min:1'],
            'allow_mobile_checkin' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $exists = AttendanceSetting::where('company_id', $this->input('company_id'))
                ->where('branch_id', $this->input('branch_id'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('branch_id', $this->input('branch_id')
                    ? 'Branch ini sudah punya attendance setting sendiri.'
                    : 'Company ini sudah punya attendance setting default.');
            }
        });
    }
}