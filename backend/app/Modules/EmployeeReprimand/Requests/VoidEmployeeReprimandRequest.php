<?php

namespace App\Modules\EmployeeReprimand\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidEmployeeReprimandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string'],
        ];
    }
}