<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th, td:nth-child(1), td:nth-child(2), td:nth-child(9) { text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>THR Detail Report</h1>
    <table>
        <thead>
            <tr>
                <th>No. Karyawan</th>
                <th>Nama</th>
                <th>Masa Kerja (bulan)</th>
                <th>Basic Salary</th>
                <th>THR Amount</th>
                <th>Deduction (BPJS)</th>
                <th>PPh21</th>
                <th>Net THR</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>{{ $row->employee_number }}</td>
                <td>{{ trim($row->first_name.' '.$row->last_name) }}</td>
                <td>{{ $row->service_months }}</td>
                <td>{{ number_format((float) $row->basic_salary, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->thr_amount, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->deduction, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->tax_amount, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->net_pay, 0, ',', '.') }}</td>
                <td>{{ $row->status }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>