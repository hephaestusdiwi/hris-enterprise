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
    <h1>PPh21 Summary Report — Periode {{ $filters['period_month'] }}/{{ $filters['period_year'] }}</h1>
    <table>
        <tr><th>Jumlah Karyawan</th><td>{{ $summary['employee_count'] }}</td></tr>
        <tr><th>Gross Earning</th><td>{{ number_format((float) $summary['gross_earning'], 0, ',', '.') }}</td></tr>
        <tr><th><strong>Total PPh21</strong></th><td><strong>{{ number_format((float) $summary['tax_amount'], 0, ',', '.') }}</strong></td></tr>
    </table>
</body>
</html>