<?php

namespace App\Modules\Offering\Enums;

enum OfferingStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired'; // di-reserve, belum ada logic auto-expire di phase ini
}