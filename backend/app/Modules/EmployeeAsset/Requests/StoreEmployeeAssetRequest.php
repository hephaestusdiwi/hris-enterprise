<?php

namespace App\Modules\EmployeeAsset\Requests;

use App\Modules\EmployeeAsset\Models\EmployeeAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_type' => ['required', Rule::in(EmployeeAsset::TYPES)],
            'asset_name' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', Rule::in(EmployeeAsset::CONDITIONS)],
            'assigned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}