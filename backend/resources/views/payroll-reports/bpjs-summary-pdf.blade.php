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
    <h1>BPJS Summary Report — Periode {{ $filters['period_month'] }}/{{ $filters['period_year'] }}</h1>
    <table>
        <tr><th>Jumlah Karyawan</th><td>{{ $summary['employee_count'] }}</td></tr>
        <tr><th>BPJS Kesehatan (Karyawan)</th><td>{{ number_format((float) $summary['kesehatan_employee'], 0, ',', '.') }}</td></tr>
        <tr><th>BPJS Kesehatan (Company)</th><td>{{ number_format((float) $summary['kesehatan_employer'], 0, ',', '.') }}</td></tr>
        <tr><th>JHT (Karyawan)</th><td>{{ number_format((float) $summary['jht_employee'], 0, ',', '.') }}</td></tr>
        <tr><th>JHT (Company)</th><td>{{ number_format((float) $summary['jht_employer'], 0, ',', '.') }}</td></tr>
        <tr><th>JKK (Company)</th><td>{{ number_format((float) $summary['jkk_employer'], 0, ',', '.') }}</td></tr>
        <tr><th>JKM (Company)</th><td>{{ number_format((float) $summary['jkm_employer'], 0, ',', '.') }}</td></tr>
        <tr><th><strong>Total BPJS Employee</strong></th><td><strong>{{ number_format((float) $summary['bpjs_employee_total'], 0, ',', '.') }}</strong></td></tr>
        <tr><th><strong>Total BPJS Company</strong></th><td><strong>{{ number_format((float) $summary['bpjs_employer_total'], 0, ',', '.') }}</strong></td></tr>
    </table>
</body>
</html>
