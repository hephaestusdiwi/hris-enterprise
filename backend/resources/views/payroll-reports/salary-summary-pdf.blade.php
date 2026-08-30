<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h1 { font-size: 16px; margin-bottom: 12px; }
        table { width: 60%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; }
        th { background: #f3f4f6; text-align: left; width: 50%; }
        td { text-align: right; }
    </style>
</head>
<body>
    <h1>Salary Summary / Recapitulation — Periode {{ $filters['period_month'] }}/{{ $filters['period_year'] }}</h1>
    <table>
        <tr><th>Jumlah Karyawan</th><td>{{ $summary['employee_count'] }}</td></tr>
        <tr><th>Basic Salary</th><td>{{ number_format((float) $summary['basic_salary'], 0, ',', '.') }}</td></tr>
        <tr><th>Allowance</th><td>{{ number_format((float) $summary['allowance_total'], 0, ',', '.') }}</td></tr>
        <tr><th>Gross Earning</th><td>{{ number_format((float) $summary['gross_earning'], 0, ',', '.') }}</td></tr>
        <tr><th>Structural Deduction</th><td>{{ number_format((float) $summary['structural_deduction'], 0, ',', '.') }}</td></tr>
        <tr><th>Manual Deduction</th><td>{{ number_format((float) $summary['manual_deduction_total'], 0, ',', '.') }}</td></tr>
        <tr><th>BPJS Employee</th><td>{{ number_format((float) $summary['bpjs_employee_total'], 0, ',', '.') }}</td></tr>
        <tr><th>BPJS Company</th><td>{{ number_format((float) $summary['bpjs_employer_total'], 0, ',', '.') }}</td></tr>
        <tr><th>PPh21</th><td>{{ number_format((float) $summary['tax_amount'], 0, ',', '.') }}</td></tr>
        <tr><th>Loan</th><td>{{ number_format((float) $summary['loan_deduction_total'], 0, ',', '.') }}</td></tr>
        <tr><th><strong>Net Pay</strong></th><td><strong>{{ number_format((float) $summary['net_pay'], 0, ',', '.') }}</strong></td></tr>
    </table>
</body>
</html>
