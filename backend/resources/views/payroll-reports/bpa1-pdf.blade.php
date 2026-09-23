<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; }
        .title { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 2px; }
        .subtitle { text-align: center; font-size: 10px; margin-bottom: 4px; }
        .note { text-align: center; font-size: 9px; color: #64748b; margin-bottom: 14px; }
        table.identity { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.identity td { padding: 2px 4px; font-size: 10.5px; vertical-align: top; }
        table.identity td.label { width: 42%; color: #475569; }
        table.identity td.colon { width: 2%; }
        .section-title { background: #f1f5f9; padding: 4px 6px; font-weight: bold; font-size: 11px; margin-top: 10px; border: 1px solid #cbd5e1; }
        table.rincian { width: 100%; border-collapse: collapse; margin-top: 0; }
        table.rincian td, table.rincian th { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 10.5px; }
        table.rincian td.no { width: 6%; text-align: center; }
        table.rincian td.desc { width: 64%; }
        table.rincian td.amount { width: 30%; text-align: right; }
        .total-row td { font-weight: bold; background: #f8fafc; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-weight: bold; font-size: 10px; }
        .status-kurang { background: #fef3c7; color: #92400e; }
        .status-lebih { background: #dbeafe; color: #1e40af; }
        .status-nihil { background: #dcfce7; color: #166534; }
        .signature { margin-top: 30px; width: 100%; }
        .signature td { width: 50%; text-align: center; vertical-align: top; font-size: 10.5px; }
        .footer-note { margin-top: 16px; font-size: 8.5px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="title">BUKTI PEMOTONGAN PAJAK PENGHASILAN PASAL 21</div>
    <div class="subtitle">BAGI PEGAWAI TETAP — TAHUN PAJAK {{ $taxYear }} (BPA1 / d.h. Formulir 1721-A1)</div>
    <div class="note">Dokumen internal perusahaan — bukan hasil cetak resmi Coretax DJP. Lihat catatan di bagian bawah.</div>

    <div class="section-title">Pemotong Pajak</div>
    <table class="identity">
        <tr><td class="label">NPWP Pemotong</td><td class="colon">:</td><td>{{ $company->npwp ?? '-' }}</td></tr>
        <tr><td class="label">Nama Pemotong</td><td class="colon">:</td><td>{{ $company->name }}</td></tr>
        <tr><td class="label">Alamat</td><td class="colon">:</td><td>{{ $company->address ?? '-' }}</td></tr>
    </table>

    <div class="section-title">A. Identitas Penerima Penghasilan</div>
    <table class="identity">
        <tr><td class="label">A.1 NIK / NPWP</td><td class="colon">:</td><td>{{ $taxProfile?->has_tax_id ? $taxProfile->tax_id_number : ($employee->national_id_number ?? '-') }}</td></tr>
        <tr><td class="label">A.2 Nama</td><td class="colon">:</td><td>{{ trim($employee->first_name.' '.$employee->last_name) }}</td></tr>
        <tr><td class="label">A.3 No. Induk Karyawan</td><td class="colon">:</td><td>{{ $employee->employee_number }}</td></tr>
        <tr><td class="label">A.4 Jenis Kelamin</td><td class="colon">:</td><td>{{ $employee->gender === 'female' ? 'Perempuan' : 'Laki-Laki' }}</td></tr>
        <tr><td class="label">A.5 Status PTKP</td><td class="colon">:</td><td>{{ $ptkpStatus?->ptkp_status?->label() ?? 'Belum diatur' }}</td></tr>
        <tr><td class="label">A.6 Nama Jabatan</td><td class="colon">:</td><td>{{ $employee->position?->name ?? '-' }}</td></tr>
        <tr><td class="label">A.7 Pegawai Asing (WNA)</td><td class="colon">:</td><td>Tidak</td></tr>
    </table>

    <div class="section-title">B. Rincian Penghasilan dan Penghitungan PPh Pasal 21 — Kode Objek Pajak 21-100-01 (Pegawai Tetap)</div>
    <table class="rincian">
        <thead>
            <tr>
                <th class="no">No.</th>
                <th class="desc">Uraian</th>
                <th class="amount">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="no">8</td>
                <td class="desc">Jumlah Penghasilan Bruto Setahun<br><span style="font-size:9px;color:#94a3b8">(gaji, tunjangan, honorarium, natura kena pajak, bonus/THR/insentif yang taxable — rincian per kategori lihat Payroll History bulanan)</span></td>
                <td class="amount">{{ number_format((float) $reconciliation->total_gross_annual, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="no">9</td>
                <td class="desc">Biaya Jabatan / Biaya Pensiun</td>
                <td class="amount">{{ number_format((float) $reconciliation->position_cost_deduction, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="no">10</td>
                <td class="desc">Iuran Pensiun / Iuran Jaminan Hari Tua</td>
                <td class="amount">{{ number_format((float) $reconciliation->pension_deduction, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="no">12</td>
                <td class="desc">Jumlah Pengurangan (9 + 10)</td>
                <td class="amount">{{ number_format((float) $reconciliation->position_cost_deduction + (float) $reconciliation->pension_deduction, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="no">13 / 15</td>
                <td class="desc">Jumlah Penghasilan Neto</td>
                <td class="amount">{{ number_format((float) $reconciliation->net_annual_income, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="no">16</td>
                <td class="desc">Penghasilan Tidak Kena Pajak (PTKP)</td>
                <td class="amount">{{ number_format((float) $reconciliation->ptkp_amount, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="no">17</td>
                <td class="desc">Penghasilan Kena Pajak (PKP)</td>
                <td class="amount">{{ number_format((float) $reconciliation->pkp, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="no">18 / 19</td>
                <td class="desc">PPh Pasal 21 Terutang Setahun (Tarif Pasal 17 UU PPh)</td>
                <td class="amount">{{ number_format((float) $reconciliation->annual_tax_pasal17, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="no">-</td>
                <td class="desc">PPh Pasal 21 Telah Dipotong Masa Sebelumnya (di pemberi kerja ini)</td>
                <td class="amount">{{ number_format((float) $reconciliation->total_withheld_prior_months, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td class="no">-</td>
                <td class="desc">PPh Pasal 21 Kurang / (Lebih) Dipotong Masa Terakhir</td>
                <td class="amount">{{ number_format((float) $reconciliation->final_period_adjustment, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 10px;">
        Status:
        @php $status = $reconciliation->status(); @endphp
        @if ($status === 'kurang_bayar')
            <span class="status-badge status-kurang">KURANG BAYAR — masih dipotong dari penghasilan masa terakhir</span>
        @elseif ($status === 'lebih_bayar')
            <span class="status-badge status-lebih">LEBIH BAYAR — wajib dikembalikan perusahaan ke karyawan</span>
        @else
            <span class="status-badge status-nihil">NIHIL</span>
        @endif
    </p>

    @if ((float) $reconciliation->gross_up_allowance > 0)
        <p style="font-size: 10px;">Termasuk tunjangan PPh (metode Gross-Up): Rp {{ number_format((float) $reconciliation->gross_up_allowance, 0, ',', '.') }}</p>
    @endif
    @if ($reconciliation->no_tax_id_surcharge_applied)
        <p style="font-size: 10px; color: #b45309;">Dikenakan tambahan tarif 20% karena karyawan belum memiliki NPWP.</p>
    @endif

    <table class="signature">
        <tr>
            <td></td>
            <td>
                {{ $company->address ? \Illuminate\Support\Str::before($company->address, ',') : '' }}, {{ now()->translatedFormat('d F Y') }}<br>
                Pemotong Pajak,<br><br><br><br>
                ( {{ $company->name }} )
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen ini dihasilkan otomatis dari sistem payroll internal berdasarkan data yang sudah dikunci (Locked) pada payroll run periode final tahun pajak {{ $taxYear }}.
        Ini BUKAN pengganti Bukti Potong BPA1 resmi yang diterbitkan lewat e-Bupot Coretax DJP — dokumen ini adalah data pendukung untuk diberikan ke karyawan
        dan bahan input manual ke Coretax. Rincian penghasilan bruto tidak dipecah per kategori (gaji/tunjangan/natura) sesuai keterbatasan sistem saat ini.
        Perhitungan "disetahunkan" untuk kondisi khusus (WNA kurang dari 12 bulan, meninggal dunia, dsb.) belum didukung.
    </div>
</body>
</html>