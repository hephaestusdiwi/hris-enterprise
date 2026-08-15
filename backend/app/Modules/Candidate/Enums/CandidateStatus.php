<?php
namespace App\Modules\Candidate\Enums;

enum CandidateStatus: string
{
    case Selected = 'selected';
    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Offering = 'offering';
    case Offered = 'offered';
    case Hold = 'hold';
    case Hire = 'hire';
    case Rejected = 'rejected';
}