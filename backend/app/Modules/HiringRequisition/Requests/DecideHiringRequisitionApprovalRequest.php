<?php

namespace App\Modules\HiringRequisition\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideHiringRequisitionApprovalRequest extends FormRequest
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