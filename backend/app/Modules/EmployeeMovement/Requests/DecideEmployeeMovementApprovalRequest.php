<?php

namespace App\Modules\EmployeeMovement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideEmployeeMovementApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi sesungguhnya dicek di Service via resolveApproverUserIds()
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
