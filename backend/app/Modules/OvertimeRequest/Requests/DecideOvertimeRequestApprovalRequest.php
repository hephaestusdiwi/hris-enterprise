<?php

namespace App\Modules\OvertimeRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideOvertimeRequestApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}