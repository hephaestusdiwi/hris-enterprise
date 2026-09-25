<?php

namespace App\Modules\Training\Enums;

/**
 * Registered -> Attended/Absent (dicatat pas/setelah sesi berlangsung)
 * -> Completed (dinyatakan lulus/selesai oleh HR). Cancelled dipakai
 * kalau peserta batal ikut sebelum sesi berlangsung.
 */
enum TrainingParticipantStatus: string
{
    case Registered = 'registered';
    case Attended = 'attended';
    case Absent = 'absent';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}