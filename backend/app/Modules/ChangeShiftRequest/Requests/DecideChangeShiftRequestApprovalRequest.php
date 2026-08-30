<?php

namespace App\Modules\ChangeShiftRequest\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideChangeShiftRequestApprovalRequest extends FormRequest
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