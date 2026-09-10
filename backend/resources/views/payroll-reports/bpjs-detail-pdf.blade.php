<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 10px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: right; }
        th, td:nth-child(1), td:nth-child(2), td:nth-child(3) { text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>BPJS Detail Report — Periode {{ $filters['period_month'] }}/{{ $filters['period_year'] }}</h1>
    <table>
        <thead>
            <tr>
                <th>No. Karyawan</th>
                <th>Nama</th>
                <th>NPP</th>
                <th>Kesehatan (Karyawan)</th>
                <th>Kesehatan (Company)</th>
                <th>JHT (Karyawan)</th>
                <th>JHT (Company)</th>
                <th>JKK (Company)</th>
                <th>JKM (Company)</th>
                <th>Total Employee</th>
                <th>Total Company</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
            <tr>
                <td>{{ $row->employee_number }}</td>
                <td>{{ trim($row->first_name.' '.$row->last_name) }}</td>
                <td>{{ $row->npp_number ?? '-' }}</td>
                <td>{{ number_format((float) $row->kesehatan_employee, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->kesehatan_employer, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->jht_employee, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->jht_employer, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->jkk_employer, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->jkm_employer, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->bpjs_employee_total, 0, ',', '.') }}</td>
                <td>{{ number_format((float) $row->bpjs_employer_total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
