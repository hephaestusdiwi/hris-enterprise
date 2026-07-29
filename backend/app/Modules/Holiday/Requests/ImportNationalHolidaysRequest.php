<?php

namespace App\Modules\Holiday\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportNationalHolidaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            // Daftar external_id yang dikonfirmasi user di step preview.
            // Jika tidak dikirim, seluruh hasil provider untuk tahun tsb akan diimport.
            'external_ids' => ['nullable', 'array'],
            'external_ids.*' => ['string'],
        ];
    }
}