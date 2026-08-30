<?php

namespace App\Modules\Attendance\Enums;

/**
 * Setiap case di sini punya write-path nyata di kode (bukan speculative) --
 * lihat AttendanceActivityService dan titik integrasinya di AttendanceService,
 * AttendanceApprovalService, AttendanceRequestService,
 * AttendanceRequestApprovalService, dan AttendanceController.
 */
enum AttendanceActivityType: string
{
    case ClockIn = 'clock_in';
    case ClockOut = 'clock_out';
    case LateDetected = 'late_detected';
    case OvertimeDetected = 'overtime_detected';
    case LateApprovalSubmitted = 'late_approval_submitted';
    case LateApproved = 'late_approved';
    case LateRejected = 'late_rejected';
    case OvertimeApprovalSubmitted = 'overtime_approval_submitted';
    case OvertimeApproved = 'overtime_approved';
    case OvertimeRejected = 'overtime_rejected';
    case AttendanceRequestSubmitted = 'attendance_request_submitted';
    case AttendanceRequestCancelled = 'attendance_request_cancelled';
    case AttendanceRequestApproved = 'attendance_request_approved';
    case AttendanceRequestRejected = 'attendance_request_rejected';
    case AttendanceCreated = 'attendance_created';
    case AttendanceCorrected = 'attendance_corrected';
    case OvertimeRequestSubmitted = 'overtime_request_submitted';
    case OvertimeRequestCancelled = 'overtime_request_cancelled';
    case OvertimeRequestApproved = 'overtime_request_approved';
    case OvertimeRequestRejected = 'overtime_request_rejected';
    case OvertimeRequestClaimed = 'overtime_request_claimed';
    case ChangeShiftRequestSubmitted = 'change_shift_request_submitted';
    case ChangeShiftRequestCancelled = 'change_shift_request_cancelled';
    case ChangeShiftRequestApproved = 'change_shift_request_approved';
    case ChangeShiftRequestRejected = 'change_shift_request_rejected';

    public function label(): string
    {
        return match ($this) {
            self::ClockIn => 'Clock In',
            self::ClockOut => 'Clock Out',
            self::LateDetected => 'Late Detected',
            self::OvertimeDetected => 'Overtime Detected',
            self::LateApprovalSubmitted => 'Late Approval Submitted',
            self::LateApproved => 'Late Approved',
            self::LateRejected => 'Late Rejected',
            self::OvertimeApprovalSubmitted => 'Overtime Approval Submitted',
            self::OvertimeApproved => 'Overtime Approved',
            self::OvertimeRejected => 'Overtime Rejected',
            self::AttendanceRequestSubmitted => 'Attendance Request Submitted',
            self::AttendanceRequestCancelled => 'Attendance Request Cancelled',
            self::AttendanceRequestApproved => 'Attendance Request Approved',
            self::AttendanceRequestRejected => 'Attendance Request Rejected',
            self::AttendanceCreated => 'Attendance Created',
            self::AttendanceCorrected => 'Attendance Corrected',
            self::OvertimeRequestSubmitted => 'Overtime Request Submitted',
            self::OvertimeRequestCancelled => 'Overtime Request Cancelled',
            self::OvertimeRequestApproved => 'Overtime Request Approved',
            self::OvertimeRequestRejected => 'Overtime Request Rejected',
            self::OvertimeRequestClaimed => 'Overtime Request Claimed',
            self::ChangeShiftRequestSubmitted => 'Change Shift Request Submitted',
            self::ChangeShiftRequestCancelled => 'Change Shift Request Cancelled',
            self::ChangeShiftRequestApproved => 'Change Shift Request Approved',
            self::ChangeShiftRequestRejected => 'Change Shift Request Rejected',
        };
    }
}