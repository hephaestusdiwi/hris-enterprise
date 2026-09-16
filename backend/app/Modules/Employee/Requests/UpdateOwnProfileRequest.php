<?php

namespace App\Modules\Employee\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 1 self-service Profile -- whitelist SENGAJA dibuat sempit.
 * TIDAK termasuk di sini (dan karena tidak ada di rules(), otomatis
 * tidak ikut ke $employee->update() lewat validated()):
 *  - 9 field lifecycle (LIFECYCLE_CONTROLLED_FIELDS di UpdateEmployeeRequest)
 *    -> tetap wajib lewat Employee Movement.
 *  - first_name/last_name/gender/birth_date/national_id_number
 *    -> data identitas, sengaja tetap view-only di Phase 1 (rawan unique
 *       constraint & pemalsuan identitas kalau self-edit bebas). Employee
 *       yang mau ubah ini kudu minta HR update lewat endpoint admin.
 *  - tax_number/bank_* -> masuk tab "Payroll" ala Talenta, belum digarap
 *    di Phase 1 ini (baru tab "General").
 *
 * authorize() true karena object-level check ("employee ini = employee
 * milik user login") sudah dilakukan di controller, konsisten dengan
 * pola AttendanceSelfServiceController.
 */
class UpdateOwnProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'birth_place' => ['nullable', 'string', 'max:255'],
            'marital_status' => ['nullable', 'in:single,married,divorced,widowed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}