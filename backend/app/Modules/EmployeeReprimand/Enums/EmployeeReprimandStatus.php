<?php

namespace App\Modules\EmployeeReprimand\Enums;

enum EmployeeReprimandStatus: string
{
    case Active = 'active';
    case Void = 'void';
}