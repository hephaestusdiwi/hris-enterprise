<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: right; }
        th, td:nth-child(1), td:nth-child(2) { text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>PPh21 Detail Report — Periode {{ $filters['period_month'] }}/{{ $filters['period_year'] }}</h1>
    <table>
        <thead>
            <tr>
                <th>No. Karyawan</th>
                <th>Nama</th>
                <th>Gross Earning</th>
                <th>PPh21</th>
                <th>Rekonsiliasi Tahunan?</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>{{ $row->employee_number }}</td>
                <td>{{ trim($row->first_name.' '.$row->last_name) }}</td>
                <td>{{ number_format((float) $row->gross_earning, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->tax_amount, 0, ',', '.') }}</td>
                <td>{{ $row->is_annual_reconciliation ? 'Ya' : 'Tidak' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>