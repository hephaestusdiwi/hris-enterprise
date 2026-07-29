<?php

namespace App\Modules\Holiday\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HolidayCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            // Opsional: kalau diisi, hasil mencakup holiday khusus company itu
            // DITAMBAH holiday global (company_id null). Kalau kosong, hanya holiday global.
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];
    }
}