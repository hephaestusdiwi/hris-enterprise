<?php

namespace App\Modules\Announcement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit announcements') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
