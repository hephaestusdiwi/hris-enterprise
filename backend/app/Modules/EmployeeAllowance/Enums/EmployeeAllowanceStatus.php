<?php

namespace App\Modules\EmployeeAllowance\Enums;

enum EmployeeAllowanceStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Processed = 'processed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Processed => 'Processed',
            self::Void => 'Void',
        };
    }
}