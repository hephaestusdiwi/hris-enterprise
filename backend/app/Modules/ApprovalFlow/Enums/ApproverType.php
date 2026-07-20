<?php

namespace App\Modules\ApprovalFlow\Enums;

enum ApproverType: string
{
    case DirectManager = 'direct_manager';
    case SpecificEmployee = 'specific_employee';
    case SpecificRole = 'specific_role';

    public function label(): string
    {
        return match ($this) {
            self::DirectManager => 'Direct Manager',
            self::SpecificEmployee => 'Specific Employee',
            self::SpecificRole => 'Specific Role',
        };
    }
}