# Arsitektur

## Kenapa Modular Monolith, bukan Microservices?

Di awal project, tim masih kecil dan requirement bisnis masih berubah cepat. Microservices di fase ini akan menambah beban operasional (banyak deployment pipeline, network latency antar service, distributed transaction, service discovery) tanpa manfaat yang sepadan.

Modular Monolith memberi kita:
- **Deployment sederhana**: satu aplikasi, satu deploy.
- **Transaksi lintas domain lebih mudah**: masih bisa pakai DB transaction biasa selama module-nya belum dipisah fisik.
- **Batas domain tetap dijaga**: karena setiap module diisolasi (folder, namespace, service layer sendiri), saat bisnis sudah scale dan salah satu module (misal Payroll atau Attendance) butuh dipisah jadi service sendiri, migrasinya jadi ekstraksi terarah — bukan bongkar total.

## Aturan Isolasi Module

Setiap module di `app/Modules/{NamaModule}/` WAJIB:
1. Punya struktur internal sendiri (Controllers, Models, Services, Repositories, Policies, Requests, Resources, Events, Listeners, Jobs, Routes, Tests).
2. Tidak boleh mengakses Model milik module lain secara langsung tanpa lewat Service/contract — untuk menjaga agar batas domain tetap jelas.
3. Komunikasi antar module lintas domain sebisa mungkin lewat Event/Listener, bukan pemanggilan langsung antar Service, supaya coupling tetap longgar.
4. Business logic ada di Service Layer, bukan di Controller. Controller hanya orchestrating: terima request tervalidasi (Form Request), panggil Service, kembalikan Resource.

## Kenapa Repository Pattern (bila diperlukan)?

Repository dipakai di module yang query-nya kompleks atau berpotensi ganti sumber data (misal nanti attendance device beda vendor). Untuk module dengan CRUD sederhana, Repository di-skip supaya tidak over-engineering (prinsip KISS) — Service langsung pakai Eloquent Model.

## Kenapa Event & Queue?

Proses yang berat atau tidak perlu blocking response (kirim notifikasi, generate payslip PDF, kalkulasi payroll masal, kompresi file) dilempar ke Queue lewat Job. Event dipakai supaya module lain bisa "mendengarkan" perubahan (misal `EmployeeHired` event didengar oleh module Payroll untuk generate salary structure default) tanpa Employee module perlu tahu detail Payroll module.

## Kenapa UUID (di beberapa tabel)?

Dipakai di entity yang berpotensi diekspos ke API publik atau disinkronkan lintas sistem/service di masa depan (misal Employee, Company), supaya ID tidak predictable dan tidak bocor informasi jumlah data. Tabel pivot/internal yang tidak pernah diekspos tetap pakai auto-increment bigint demi performa index.

## Kenapa PostgreSQL 17?

Dipilih karena fitur-fitur berikut relevan untuk kebutuhan HRIS:
- **JSONB**: menyimpan data semi-terstruktur yang skema-nya bisa berbeda per baris, misal custom fields di form Recruitment/Performance.
- **Generated Column & Composite Index**: berguna untuk kolom turunan (misal nama lengkap, atau status terhitung) dan query multi-kolom yang sering dipakai bareng (misal `employee_id + period` di Payroll).
- **Full Text Search**: pencarian karyawan/dokumen tanpa perlu Elasticsearch di fase awal.
- **Window Function & CTE**: laporan analitik (attendance summary, payroll report) yang butuh agregasi bertingkat.
- **Materialized View**: dashboard analytics yang query-nya berat tapi datanya tidak perlu real-time detik itu juga.

Fitur-fitur ini dipakai hanya kalau memang memberi manfaat nyata di module terkait — tidak dipaksakan di semua tabel.

## Modul yang Direncanakan

Auth, Employee, Attendance, Payroll, Leave, Recruitment, Performance, Shift, Schedule, Approval, Asset, Training, Notification, Report, Dashboard, Setting.

Detail urutan pengerjaan tiap module ada di [`ROADMAP.md`](ROADMAP.md).
