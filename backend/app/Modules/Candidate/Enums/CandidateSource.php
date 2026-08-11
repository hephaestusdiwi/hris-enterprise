<?php
namespace App\Modules\Candidate\Enums;

enum CandidateSource: string
{
    case CareerSite = 'career_site';
    case LinkedIn = 'linkedin';
    case JobStreet = 'jobstreet';
    case Referral = 'referral';
    case Import = 'import';
    case TalentPool = 'talent_pool';
    case Other = 'other';
}