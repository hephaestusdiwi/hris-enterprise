<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; margin-bottom: 2px; }
        .sub { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 10px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #666; margin-top: 16px; margin-bottom: 4px; }
        .line-row td { border-bottom: 1px solid #eee; }
        .line-row td:last-child { text-align: right; }
        .total-row td { border-top: 2px solid #333; font-weight: bold; padding-top: 8px; }
        .total-row td:last-child { text-align: right; }
    </style>
</head>
<body>
    <h1>Payslip — {{ $payslip->employee->first_name }} {{ $payslip->employee->last_name }}</h1>
    <p class="sub">{{ $payslip->employee->employee_number }} · Periode {{ $payslip->payrollRun->period_month }}/{{ $payslip->payrollRun->period_year }}</p>

    <p class="section-title">Rincian Komponen</p>
    <table>
        @foreach ($payslip->lines as $line)
        <tr class="line-row">
            <td>{{ $line->label }}</td>
            <td>{{ number_format((float) $line->amount, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>

    <p class="section-title">Ringkasan</p>
    <table>
        <tr class="line-row"><td>Gross Earning</td><td>{{ number_format((float) $payslip->gross_earning, 0, ',', '.') }}</td></tr>
        <tr class="line-row"><td>Structural Deduction</td><td>{{ number_format((float) $payslip->structural_deduction, 0, ',', '.') }}</td></tr>
        <tr class="line-row"><td>Manual Deduction</td><td>{{ number_format((float) $payslip->manual_deduction_total, 0, ',', '.') }}</td></tr>
        <tr class="line-row"><td>BPJS (Karyawan)</td><td>{{ number_format((float) $payslip->bpjs_employee_total, 0, ',', '.') }}</td></tr>
        <tr class="line-row"><td>PPh 21</td><td>{{ number_format((float) $payslip->tax_amount, 0, ',', '.') }}</td></tr>
        <tr class="line-row"><td>Loan</td><td>{{ number_format((float) $payslip->loan_deduction_total, 0, ',', '.') }}</td></tr>
        <tr class="total-row"><td>Net Pay</td><td>{{ number_format((float) $payslip->net_pay, 0, ',', '.') }}</td></tr>
    </table>
</body>
</html>