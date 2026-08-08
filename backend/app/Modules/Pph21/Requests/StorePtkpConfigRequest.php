<?php

namespace App\Modules\Pph21\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePtkpConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ptkp_status' => ['required', Rule::in(['tk0', 'tk1', 'tk2', 'tk3', 'k0', 'k1', 'k2', 'k3'])],
            'effective_date' => ['required', 'date'],
            'annual_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}