<?php

namespace App\Modules\WorkingSchedule\Requests;

use App\Modules\WorkingSchedule\Enums\WorkingScheduleTargetType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkingScheduleAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'working_schedule_id' => ['required', 'exists:working_schedules,id'],
            'target_type' => ['required', Rule::enum(WorkingScheduleTargetType::class)],
            'target_id' => ['required', 'integer'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $targetType = $this->input('target_type');
            $targetId = $this->input('target_id');
            $workingScheduleId = $this->input('working_schedule_id');

            if (! $targetType || ! $targetId || ! $workingScheduleId) {
                return;
            }

            $schedule = \App\Modules\WorkingSchedule\Models\WorkingSchedule::find($workingScheduleId);

            if (! $schedule) {
                return;
            }

            $targetCompanyId = match ($targetType) {
                'company' => \App\Modules\Company\Models\Company::find($targetId)?->id,
                'branch' => \App\Modules\Branch\Models\Branch::find($targetId)?->company_id,
                'department' => \App\Modules\Department\Models\Department::find($targetId)?->company_id,
                'position' => \App\Modules\Position\Models\Position::find($targetId)?->company_id,
                default => null,
            };

            if ($targetCompanyId === null) {
                $validator->errors()->add('target_id', 'Target tidak ditemukan.');

                return;
            }

            if ($targetCompanyId !== $schedule->company_id) {
                $validator->errors()->add('target_id', 'Target harus berada di company yang sama dengan Working Schedule.');
            }
        });
    }
}