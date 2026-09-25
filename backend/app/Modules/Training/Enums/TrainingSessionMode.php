<?php
 
namespace App\Modules\Training\Enums;

enum TrainingSessionMode: string
{
    case Online = 'online';
    case Offline = 'offline';
    case Hybrid = 'hybrid';
}