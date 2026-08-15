<?php

namespace App\Modules\NewJoiner\Enums;

enum NewJoinerStatus: string
{
    case Sent = 'sent';           // VERIFIED — nama status asli dari halaman New Joiner Submission Talenta
    case Submitted = 'submitted'; // VERIFIED — nama status asli dari halaman New Joiner Submission Talenta
}