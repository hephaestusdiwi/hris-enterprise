<?php

namespace App\Modules\LeaveRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideLeaveApprovalRequest extends FormRequest
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