<?php

namespace App\Modules\Announcement\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit announcements') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'announcement_category_id' => ['required', 'exists:announcement_categories,id'],
            'target_type' => ['required', Rule::in(['all', 'criteria'])],
            'targets' => ['required_if:target_type,criteria', 'array'],
            'targets.*.type' => ['required_with:targets', Rule::in(['branch', 'department', 'position', 'job_level'])],
            'targets.*.id' => ['required_with:targets', 'integer'],
        ];
    }
}
