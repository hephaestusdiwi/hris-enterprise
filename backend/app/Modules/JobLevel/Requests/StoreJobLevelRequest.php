<?php

namespace App\Modules\JobLevel\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('job_levels', 'code')->where('company_id', $this->input('company_id')),
            ],
            'level_order' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }
}