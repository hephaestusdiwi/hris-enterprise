<?php

namespace App\Modules\AttendanceRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideAttendanceRequestApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
