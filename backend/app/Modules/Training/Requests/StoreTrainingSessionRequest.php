<?php

namespace App\Modules\Training\Requests;

use App\Modules\Training\Enums\TrainingSessionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'trainer_employee_id' => ['nullable', 'exists:employees,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'mode' => ['required', Rule::enum(TrainingSessionMode::class)],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}