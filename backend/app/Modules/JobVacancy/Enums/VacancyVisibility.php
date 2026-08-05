<?php

namespace App\Modules\JobVacancy\Enums;

enum VacancyVisibility: string
{
    case Internal = 'internal';
    case External = 'external';
    case Both = 'both';
}