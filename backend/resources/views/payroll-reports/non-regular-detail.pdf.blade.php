<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th, td:nth-child(1), td:nth-child(2), td:nth-child(3), td:nth-child(4), td:nth-child(6), td:nth-child(7), td:nth-child(9) { text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>Non-Regular Payroll Detail Report</h1>
    <table>
        <thead>
            <tr>
                <th>No. Karyawan</th>
                <th>Nama</th>
                <th>Component</th>
                <th>Kategori</th>
                <th>Amount</th>
                <th>Earning/Deduction</th>
                <th>Taxable</th>
                <th>Net Payslip</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>{{ $row->employee_number }}</td>
                <td>{{ trim($row->first_name.' '.$row->last_name) }}</td>
                <td>{{ $row->component_name }}</td>
                <td>{{ $row->category }}</td>
                <td>{{ number_format((float) $row->amount, 0, ',', '.') }}</td>
                <td>{{ $row->is_addition ? 'Earning' : 'Deduction' }}</td>
                <td>{{ $row->is_taxable ? 'Ya' : 'Tidak' }}</td>
                <td>{{ number_format((float) $row->net_pay, 0, ',', '.') }}</td>
                <td>{{ $row->status?->value ?? $row->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>