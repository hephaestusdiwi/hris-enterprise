<?php

namespace App\Modules\Position\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'parent_position_id' => ['nullable', 'exists:positions,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('positions', 'code')->where('company_id', $this->input('company_id')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}