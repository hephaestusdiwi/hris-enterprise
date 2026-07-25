<?php

namespace App\Modules\Attendance\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceReportExport implements FromArray, WithHeadings
{
    public function __construct(private array $summary)
    {
    }

    public function headings(): array
    {
        return [
            'No. Karyawan', 'Nama', 'Present', 'Late', 'Overtime (menit)',
            'Leave', 'Absent', 'Expected Working Days', 'Working Hours', 'Attendance Rate (%)',
        ];
    }

    public function array(): array
    {
        return array_map(fn (array $row) => [
            $row['employee']['employee_number'],
            $row['employee']['name'],
            $row['present_days'],
            $row['late_days'],
            $row['overtime_minutes'],
            $row['leave_days'],
            $row['absent_days'],
            $row['expected_working_days'],
            $row['working_hours'],
            $row['attendance_rate'],
        ], $this->summary);
    }
}