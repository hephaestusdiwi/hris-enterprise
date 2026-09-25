<?php

namespace App\Modules\Training\Requests;

use App\Modules\Training\Enums\TrainingProgramStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `type` TIDAK termasuk field yang bisa diubah (pola sama dengan
 * CompanyObligation::type immutable) -- kalau tipenya beda (internal
 * jadi eksternal), harusnya program baru, bukan edit yang lama.
 */
class UpdateTrainingProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'required', 'exists:companies,id'],
            'training_category_id' => ['sometimes', 'required', 'exists:training_categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'pic_employee_id' => ['nullable', 'exists:employees,id'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'required', Rule::enum(TrainingProgramStatus::class)],
        ];
    }
}