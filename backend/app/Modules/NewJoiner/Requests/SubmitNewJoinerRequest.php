<?php

namespace App\Modules\NewJoiner\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitNewJoinerRequest extends FormRequest
{
    public function authorize(): bool { return true; } // publik, akses via token

    public function rules(): array
    {
        return [
            'gender' => ['required', 'in:male,female'],
            'birth_place' => ['required', 'string'],
            'birth_date' => ['required', 'date'],
            'marital_status' => ['required', 'in:single,married,divorced,widowed'],
            'address' => ['required', 'string'],
            'emergency_contact_name' => ['required', 'string'],
            'emergency_contact_phone' => ['required', 'string'],
            'national_id_number' => ['required', 'string'],
            'tax_number' => ['nullable', 'string'],
            'bank_name' => ['required', 'string'],
            'bank_account_number' => ['required', 'string'],
            'bank_account_holder_name' => ['required', 'string'],
        ];
    }
}