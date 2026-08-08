<?php

namespace App\Modules\Pph21\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeePtkpStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ptkp_status' => ['required', Rule::in(['tk0', 'tk1', 'tk2', 'tk3', 'k0', 'k1', 'k2', 'k3'])],
            'tax_year' => ['required', 'integer', 'min:2020', 'max:2100'],
        ];
    }
}