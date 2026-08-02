<?php

namespace App\Modules\AttendanceSetting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaceRecognitionTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi ditangani middleware 'permission:edit attendance settings' di route
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'image_base64' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee wajib dipilih.',
            'employee_id.exists' => 'Employee tidak ditemukan.',
            'image_base64.required' => 'Foto wajib diambil terlebih dahulu.',
        ];
    }
}