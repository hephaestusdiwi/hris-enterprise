<?php

namespace App\Modules\Training\Requests;

use App\Modules\Training\Enums\TrainingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'training_category_id' => ['required', 'exists:training_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(TrainingType::class)],
            'organizer' => ['nullable', 'string', 'max:255'],
            'pic_employee_id' => ['nullable', 'exists:employees,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}