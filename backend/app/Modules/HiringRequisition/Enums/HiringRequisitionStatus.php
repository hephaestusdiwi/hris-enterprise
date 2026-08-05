<?php

namespace App\Modules\HiringRequisition\Enums;

enum HiringRequisitionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Open = 'open';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Closed = 'closed';
}