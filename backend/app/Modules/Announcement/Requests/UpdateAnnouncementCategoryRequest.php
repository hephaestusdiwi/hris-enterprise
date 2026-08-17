<?php

namespace App\Modules\Announcement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit announcements') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('announcement_categories', 'code')->ignore($this->route('announcementCategory')),
            ],
            'is_active' => ['boolean'],
        ];
    }
}
