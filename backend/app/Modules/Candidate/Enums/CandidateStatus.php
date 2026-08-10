<?php
namespace App\Modules\Candidate\Enums;

enum CandidateStatus: string
{
    case Applied = 'applied';
    case Screening = 'screening';
    case Interview = 'interview';
    case Hold = 'hold';
    case Hire = 'hire';
    case Rejected = 'rejected';
}