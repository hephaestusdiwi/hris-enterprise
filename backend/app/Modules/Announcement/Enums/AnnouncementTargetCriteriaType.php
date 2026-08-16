<?php

namespace App\Modules\Announcement\Enums;

enum AnnouncementTargetCriteriaType: string
{
    case Branch = 'branch';
    case Department = 'department';
    case Position = 'position';
    case JobLevel = 'job_level';

    /**
     * Kolom FK di tabel employees yang relevan buat resolve target criteria
     * jenis ini.
     */
    public function employeeColumn(): string
    {
        return match ($this) {
            self::Branch => 'branch_id',
            self::Department => 'department_id',
            self::Position => 'position_id',
            self::JobLevel => 'job_level_id',
        };
    }
}
