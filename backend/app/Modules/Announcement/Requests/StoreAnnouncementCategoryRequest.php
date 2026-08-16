<?php

namespace App\Modules\Announcement\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create announcements') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:announcement_categories,code'],
            'is_active' => ['boolean'],
        ];
    }
}
