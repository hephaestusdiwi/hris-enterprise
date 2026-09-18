<?php

namespace App\Modules\EmployeeAsset\Requests;

use App\Modules\EmployeeAsset\Models\EmployeeAsset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_name' => ['sometimes', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'condition' => ['sometimes', Rule::in(EmployeeAsset::CONDITIONS)],
            'returned_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}