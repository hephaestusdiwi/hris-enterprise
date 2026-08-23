<?php

namespace App\Modules\Expense\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpensePolicyAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Sengaja HANYA is_active + expiration_date -- employee_id,
        // expense_policy_id, effective_date TIDAK boleh diubah lewat PUT
        // (ganti policy = bikin assignment baru, riwayat lama tidak
        // pernah ditimpa). Karena rules() cuma deklarasi 2 field ini,
        // $request->validated() otomatis tidak akan pernah berisi field
        // lain walau dikirim di body request.
        $assignment = $this->route('expensePolicyAssignment');

        $effectiveDate = $assignment?->effective_date?->toDateString();

        return [
            'is_active' => ['boolean'],
            'expiration_date' => array_filter([
                'nullable',
                'date',
                $effectiveDate ? "after_or_equal:{$effectiveDate}" : null,
            ]),
        ];
    }
}