<?php

namespace App\Modules\Announcement\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
