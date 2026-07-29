<?php

namespace App\Modules\Holiday\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewNationalHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}