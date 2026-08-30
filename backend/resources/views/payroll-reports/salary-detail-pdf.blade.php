<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th, td:nth-child(1), td:nth-child(2) { text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Salary Detail Report — Periode {{ $filters['period_month'] }}/{{ $filters['period_year'] }}</h1>
    <table>
        <thead>
            <tr>
                <th>No. Karyawan</th>
                <th>Nama</th>
                <th>Basic Salary</th>
                <th>Allowance</th>
                <th>Gross Earning</th>
                <th>Structural Ded.</th>
                <th>Manual Ded.</th>
                <th>BPJS Employee</th>
                <th>BPJS Company</th>
                <th>PPh21</th>
                <th>Loan</th>
                <th>Net Pay</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>{{ $row->employee_number }}</td>
                <td>{{ trim($row->first_name.' '.$row->last_name) }}</td>
                <td>{{ number_format((float) $row->basic_salary, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->allowance_total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->gross_earning, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->structural_deduction, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->manual_deduction_total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->bpjs_employee_total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->bpjs_employer_total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->tax_amount, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->loan_deduction_total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->net_pay, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
