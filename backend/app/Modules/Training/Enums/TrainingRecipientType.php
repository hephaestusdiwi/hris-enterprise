<?php

namespace App\Modules\Training\Enums;

enum TrainingRecipientType: string
{
    case User ='user';
    case Pic = 'pic';
    case Role = 'role';
}