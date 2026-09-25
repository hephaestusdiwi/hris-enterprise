<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 15mm 13mm 14mm 13mm; }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #333;
            font-size: 8px;
            line-height: 1.15;
        }

        table { width: 100%; border-collapse: collapse; border-spacing: 0; }
        td, th { margin: 0; padding: 0; }

        .header { margin-bottom: 10px; }
        .header-left { width: 65%; vertical-align: top; }
        .header-right { width: 35%; vertical-align: top; text-align: right; }

        .logo { display: block; max-width: 120px; max-height: 36px; margin-bottom: 6px; }
        .company-name { color: #202020; font-size: 11.5px; font-weight: 700; line-height: 1; }
        .company-address { color: #888; font-size: 7px; line-height: 1.1; margin-top: 3px; }

        .confidential { color: #999; font-size: 6.5px; line-height: 1; margin: 1px 0 17px; }
        .payslip-title { color: #222; font-size: 14px; font-weight: 700; line-height: 1; }
        .pay-period { color: #777; font-size: 7px; line-height: 1; margin-top: 4px; }
        .header-rule {
            display: none;
        }

        .section-title {
            color: #222222;
            font-size: 7.8px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
            margin: 9px 0 5px;
        }

        .payroll-info {
            table-layout: fixed;
            width: 100%;
            margin: 0 0 10px 0;
            border-top: 1px solid #dedede;
            border-bottom: 1px solid #dedede;
        }

        .payroll-info td {
            width: 25%;
            vertical-align: middle;
            text-align: left !important;
            padding: 5px 8px;
        }

        .payroll-info tr:first-child td {
            padding-top: 6px;
            padding-bottom: 4px;
        }

        .payroll-info tr:last-child td {
            padding-top: 4px;
            padding-bottom: 6px;
        }

        .payroll-info td:last-child {
            padding-right: 8px;
        }

        .info-label {
            display: block;
            width: 100%;
            color: #8a8a8a;
            font-size: 6.2px;
            font-weight: 400;
            line-height: 1;
            text-transform: uppercase;
            text-align: left !important;
            margin: 0 0 2px 0;
        }

        .info-value {
            display: block;
            width: 100%;
            color: #333333;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.05;
            text-align: left !important;
            white-space: normal;
        }

        .main-grid, .lower-grid { table-layout: fixed; }
        .main-grid > tbody > tr > td,
        .lower-grid > tbody > tr > td { width: 50%; vertical-align: top; }

        .main-grid .left, .lower-grid .left { padding-right: 5px; }
        .main-grid .right, .lower-grid .right { padding-left: 5px; }

        .pay-box, .lower-box {
            border: 1px solid #d6d6d6;
            width: 100%;
        }

        .box-header, .lower-header {
            background: #e7e7e7;
            color: #222;
            font-size: 8px;
            font-weight: 700;
            line-height: 1;
            padding: 6px 8px;
        }

        .pay-lines, .lower-lines { table-layout: fixed; }

        .pay-lines td {
            padding: 4px 8px;
            color: #777;
            font-size: 8px;
            line-height: 1.05;
        }
        .pay-lines td:first-child { width: 67%; }
        .pay-lines td:last-child {
            width: 33%;
            text-align: right;
            color: #555;
            white-space: nowrap;
        }
        .pay-lines .empty td { color: #999; }
        .pay-lines .total td {
            border-top: 1px solid #d9d9d9;
            padding-top: 6px;
            padding-bottom: 7px;
            color: #222;
            font-weight: 700;
        }

        .thp {
            margin-top: 5px;
            border: 1px solid #d6d6d6;
            table-layout: fixed;
        }
        .thp td { padding: 6px 8px; vertical-align: middle; }
        .thp .label {
            width: 58%;
            color: #222;
            font-size: 9px;
            font-weight: 700;
            line-height: 1;
        }
        .thp .amount {
            width: 42%;
            color: #222;
            font-size: 9.5px;
            font-weight: 700;
            text-align: right;
            line-height: 1;
            white-space: nowrap;
        }

        .lower-grid { margin-top: 12px; }
        .lower-header {
            background: transparent;
            border-bottom: 1px solid #ddd;
        }
        .lower-lines td {
            padding: 3px 8px;
            color: #777;
            font-size: 7.8px;
            line-height: 1.05;
        }
        .lower-lines td:first-child { width: 70%; }
        .lower-lines td:last-child {
            width: 30%;
            text-align: right;
            color: #555;
            white-space: nowrap;
        }
        .lower-lines .total td {
            border-top: 1px solid #ddd;
            padding-top: 5px;
            padding-bottom: 6px;
            color: #222;
            font-weight: 700;
        }

        .note-wrap { margin-top: 12px; }
        .note-title {
            color: #222;
            font-size: 8px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 4px;
        }
        .note-box {
            border: 1px solid #d6d6d6;
            padding: 6px 8px;
            color: #777;
            font-size: 7.4px;
            line-height: 1.15;
        }

        .footnote, .disclaimer {
            color: #999;
            font-size: 6.4px;
            line-height: 1.25;
            margin-top: 6px;
        }

        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #eee;
        }
        .footer td {
            color: #999;
            font-size: 6.5px;
            line-height: 1;
        }
        .footer .left { width: 70%; }
        .footer .right { width: 30%; text-align: right; }
    </style>
</head>

<body>
@php
    $employee = $payslip->employee;
    $payrollRun = $payslip->payrollRun;

    $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $period = str_pad((string) $payrollRun->period_month, 2, '0', STR_PAD_LEFT)
        . '/' . $payrollRun->period_year;

    $employeeNumber = $employee->employee_number ?? '-';
    $position = data_get($employee, 'position.name', '-');
    $department = data_get($employee, 'department.name', '-');
    $company = data_get($employee, 'company.name')
        ?? data_get($payrollRun, 'company.name')
        ?? 'Company';
    $companyAddress = data_get($employee, 'company.address')
        ?? data_get($payrollRun, 'company.address')
        ?? '';

    $companyLogo = data_get($employee, 'company.logo_url')
        ?? data_get($payrollRun, 'company.logo_url');

    $npwp = $employee->tax_number ?? '-';
    $ptkpStatus = $ptkpStatus ?? '-';

    /*
     * PTKP resolver may return values such as tk0 / k1.
     * Payslip displays the standard PTKP notation: TK/0, K/1, etc.
     */
    $ptkpRaw = strtoupper(trim((string) $ptkpStatus));

    if (preg_match('/^(TK|K)[\\/\\-_]?([0-3])$/', $ptkpRaw, $matches)) {
        $ptkpStatus = $matches[1] . '/' . $matches[2];
    }

    $joinDate = data_get($employee, 'join_date');

    $grossEarning = (float) $payslip->gross_earning;
    $structuralDeduction = (float) ($payslip->structural_deduction ?? 0);
    $manualDeduction = (float) ($payslip->manual_deduction_total ?? 0);
    $bpjsEmployee = (float) ($payslip->bpjs_employee_total ?? 0);
    $taxAmount = (float) ($payslip->tax_amount ?? 0);
    $loanDeduction = (float) ($payslip->loan_deduction_total ?? 0);
    $netPay = (float) $payslip->net_pay;

    $totalDeductions = $structuralDeduction
        + $manualDeduction
        + $bpjsEmployee
        + $taxAmount
        + $loanDeduction;

    $formatMoney = fn ($value) => number_format((float) $value, 0, ',', '.');

    $earningLines = $payslip->lines->filter(
        fn ($line) => $line->type?->value === 'earning'
    );

    $deductionLines = $payslip->lines->filter(
        fn ($line) => in_array($line->type?->value, [
            'deduction',
            'bpjs_employee',
            'tax',
            'loan_installment',
        ], true)
    );

    $employerLines = $payslip->lines->filter(
        fn ($line) => $line->type?->value === 'bpjs_employer'
    );

    $totalBenefits = $employerLines->sum(
        fn ($line) => (float) $line->amount
    );

    /*
     * Attendance summary supplied by PayslipController.
     * All numeric defaults are 0 so an actual zero is shown as 0,
     * not as a misleading "-".
     */
    $attendanceSummary = $attendanceSummary ?? [];

    $actualWorkingDays =
        (int) ($attendanceSummary['present_days'] ?? 0)
        + (int) ($attendanceSummary['late_days'] ?? 0);

    $scheduleWorkingDays =
        (int) ($attendanceSummary['expected_working_days'] ?? 0);

    $dayoffDays =
        (int) ($attendanceSummary['dayoff_days'] ?? 0);

    $holidayDays =
        (int) ($attendanceSummary['holiday_days'] ?? 0);

    $absentDays =
        (int) ($attendanceSummary['absent_days'] ?? 0);

    $leaveDays =
        (int) ($attendanceSummary['leave_days'] ?? 0);

    $workingHours =
        (float) ($attendanceSummary['working_hours'] ?? 0);

    $overtimeMinutes =
        (int) ($attendanceSummary['overtime_minutes'] ?? 0);

    $attendanceRate =
        $attendanceSummary['attendance_rate'] ?? 0;
@endphp

<table class="header">
    <tr>
        <td class="header-left">
            @if($companyLogo)
                <img src="{{ $companyLogo }}" class="logo" alt="Company Logo">
            @endif

            <div class="company-name">{{ $company }}</div>

            @if($companyAddress && $companyAddress !== '-')
                <div class="company-address">{{ $companyAddress }}</div>
            @endif
        </td>

        <td class="header-right">
            <div class="confidential">*CONFIDENTIAL</div>
            <div class="payslip-title">PAYSLIP</div>
            <div class="pay-period">Pay Period: {{ $period }}</div>
        </td>
    </tr>
</table>

<div class="header-rule"></div>

<table class="payroll-info">
    <tr>
        <td>
            <div class="info-label">Payroll Cut Off</div>
            <div class="info-value">{{ data_get($payrollRun, 'payroll_cut_off', $period) }}</div>
        </td>
        <td>
            <div class="info-label">ID / Name</div>
            <div class="info-value">{{ $employeeNumber }} / {{ $employeeName }}</div>
        </td>
        <td>
            <div class="info-label">Job Position</div>
            <div class="info-value">{{ $position }}</div>
        </td>
        <td>
            <div class="info-label">PTKP</div>
            <div class="info-value">{{ $ptkpStatus }}</div>
        </td>
    </tr>

    <tr>
        <td>
            <div class="info-label">Organization</div>
            <div class="info-value">{{ $department }}</div>
        </td>
        <td>
            <div class="info-label">Grade / Level</div>
            <div class="info-value">
                {{ data_get($employee, 'jobLevel.name', data_get($employee, 'grade.name', '-')) }}
            </div>
        </td>
        <td>
            <div class="info-label">NPWP</div>
            <div class="info-value">{{ $npwp }}</div>
        </td>
        <td>
            <div class="info-label">Join Date</div>
            <div class="info-value">
                @if($joinDate)
                    {{ \Carbon\Carbon::parse($joinDate)->format('d M Y') }}
                @else
                    -
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="main-grid">
    <tr>
        <td class="left">
            <div class="pay-box">
                <div class="box-header">Earnings</div>

                <table class="pay-lines">
                    @forelse($earningLines as $line)
                        <tr>
                            <td>{{ $line->label }}</td>
                            <td>{{ $formatMoney($line->amount) }}</td>
                        </tr>
                    @empty
                        <tr class="empty">
                            <td colspan="2">-</td>
                        </tr>
                    @endforelse

                    <tr class="total">
                        <td>Total Earnings</td>
                        <td>{{ $formatMoney($grossEarning) }}</td>
                    </tr>
                </table>
            </div>
        </td>

        <td class="right">
            <div class="pay-box">
                <div class="box-header">Deductions</div>

                <table class="pay-lines">
                    @forelse($deductionLines as $line)
                        <tr>
                            <td>{{ $line->label }}</td>
                            <td>{{ $formatMoney($line->amount) }}</td>
                        </tr>
                    @empty
                        <tr class="empty">
                            <td colspan="2">-</td>
                        </tr>
                    @endforelse

                    @if($structuralDeduction > 0)
                        <tr>
                            <td>Structural Deduction</td>
                            <td>{{ $formatMoney($structuralDeduction) }}</td>
                        </tr>
                    @endif

                    @if($manualDeduction > 0)
                        <tr>
                            <td>Other Deduction</td>
                            <td>{{ $formatMoney($manualDeduction) }}</td>
                        </tr>
                    @endif

                    <tr class="total">
                        <td>Total Deductions</td>
                        <td>{{ $formatMoney($totalDeductions) }}</td>
                    </tr>
                </table>
            </div>

            <table class="thp">
                <tr>
                    <td class="label">Take Home Pay</td>
                    <td class="amount">Rp{{ $formatMoney($netPay) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="lower-grid">
    <tr>
        <td class="left">
            <div class="lower-box">
                <div class="lower-header">Benefits*</div>

                <table class="lower-lines">
                    @forelse($employerLines as $line)
                        <tr>
                            <td>{{ $line->label }}</td>
                            <td>{{ $formatMoney($line->amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td>-</td>
                            <td>-</td>
                        </tr>
                    @endforelse

                    <tr class="total">
                        <td>Total Benefits</td>
                        <td>{{ $formatMoney($totalBenefits) }}</td>
                    </tr>
                </table>
            </div>
        </td>

        <td class="right">
            <div class="lower-box">
                <div class="lower-header">Attendance Summary</div>

                <table class="lower-lines">
                    <tr>
                        <td>Actual Working Day</td>
                        <td>{{ $actualWorkingDays }}</td>
                    </tr>
                    <tr>
                        <td>Schedule Working Day</td>
                        <td>{{ $scheduleWorkingDays }}</td>
                    </tr>
                    <tr>
                        <td>Dayoff</td>
                        <td>{{ $dayoffDays }}</td>
                    </tr>
                    <tr>
                        <td>Holiday</td>
                        <td>{{ $holidayDays }}</td>
                    </tr>
                    <tr>
                        <td>Absent</td>
                        <td>{{ $absentDays }}</td>
                    </tr>
                    <tr>
                        <td>Leave</td>
                        <td>{{ $leaveDays }}</td>
                    </tr>
                    <tr>
                        <td>Working Hours</td>
                        <td>{{ number_format($workingHours, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td>Overtime</td>
                        <td>{{ $overtimeMinutes }} min</td>
                    </tr>
                    <tr>
                        <td>Attendance Rate</td>
                        <td>{{ number_format((float) $attendanceRate, 2, ',', '.') }}%</td>
                    </tr>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="note-wrap">
    <div class="note-title">Note:</div>

    <div class="note-box">
        {{ data_get(
            $payslip,
            'note',
            'This payslip is generated electronically and does not require a signature.'
        ) }}
    </div>
</div>

<div class="footnote">
    *These are the benefits you''ll get from the company, but are not included in your take-home pay (THP).
</div>

<div class="disclaimer">
    This is a computer generated printout and no signature is required.
</div>

<table class="footer">
    <tr>
        <td class="left">
            This payslip is generated by {{ $company }}
        </td>
        <td class="right">
            {{ $period }}
        </td>
    </tr>
</table>

</body>
</html>
