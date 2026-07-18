<?php

namespace App\Modules\Position\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'parent_position_id' => [
                'nullable',
                'exists:positions,id',
                function ($attribute, $value, $fail) {
                    if ($value && (int) $value === (int) $this->route('position')->id) {
                        $fail('Posisi tidak dapat menjadi induk dari dirinya sendiri.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('positions', 'code')
                    ->where('company_id', $this->input('company_id'))
                    ->ignore($this->route('position')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}