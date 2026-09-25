<?php

namespace App\Modules\Training\Requests;

use App\Modules\Training\Enums\TrainingSessionMode;
use App\Modules\Training\Enums\TrainingSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'trainer_name' => ['nullable', 'string', 'max:255'],
            'trainer_employee_id' => ['nullable', 'exists:employees,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'mode' => ['sometimes', 'required', Rule::enum(TrainingSessionMode::class)],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::enum(TrainingSessionStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}