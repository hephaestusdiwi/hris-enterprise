<?php

namespace App\Modules\LeaveBalance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveBalanceAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'adjustment_days' => ['required', 'numeric'],
            'reason' => ['required', 'string'],
        ];
    }
}