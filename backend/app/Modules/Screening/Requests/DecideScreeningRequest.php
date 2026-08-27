<?php

namespace App\Modules\Screening\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecideScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', 'string', 'in:passed,failed,hold'],
            'notes' => ['nullable', 'string'],
        ];
    }
}