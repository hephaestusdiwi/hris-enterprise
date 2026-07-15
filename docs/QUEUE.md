# Queue

## Kenapa `REDIS_PASSWORD` Ditambahin ke `php`, `queue`, `scheduler`?

Sejak STEP 7, Redis wajib auth (`requirepass`). Tanpa `REDIS_PASSWORD` di environment ketiga container ini, koneksi queue/cache dari Laravel bakal ditolak begitu STEP 11 (Laravel Installation) selesai. Ditambahin dari sekarang supaya nggak lupa nanti.

## Kenapa `queue:work`, Bukan `queue:listen`?

`queue:work` load framework Laravel sekali di awal lalu proses job berulang dalam satu proses PHP — jauh lebih hemat resource dibanding `queue:listen` yang boot ulang framework tiap job. Konsekuensinya: kalau ada perubahan kode, worker perlu di-restart manual (`docker compose restart queue`) supaya kode baru kebaca — ini nanti dimasukkan ke deploy script pas STEP 96 (Production Docker).

## Kenapa `--tries=3 --backoff=5`?

Job yang gagal dicoba ulang maksimal 3 kali, jeda 5 detik antar percobaan — cukup untuk menoleransi kegagalan sesaat (misal koneksi API pihak ketiga lagi flaky) tanpa bikin job yang memang cacat looping selamanya. Job yang tetap gagal setelah 3 percobaan masuk `failed_jobs` table (dibuat otomatis oleh migration Laravel di STEP 11) untuk diperiksa manual.

## Kenapa `--max-time=3600`?

Worker PHP yang jalan lama tanpa restart rawan memory leak (baik dari kode aplikasi maupun package pihak ketiga). Dengan `--max-time=3600`, worker otomatis exit tiap 1 jam, lalu `restart: unless-stopped` di Docker langsung menghidupkannya lagi — proses ini nyaris instan dan tidak mengganggu job yang sedang diproses (job yang lagi jalan tetap diselesaikan dulu sebelum worker exit).

## Kenapa `stop_grace_period: 30s`?

Default Docker cuma kasih 10 detik antara `SIGTERM` dan `SIGKILL` paksa saat container di-stop. Kalau pas itu ada job berat lagi jalan (misal Payroll Generator di STEP 54, yang mungkin butuh waktu lebih dari 10 detik untuk selesai), job itu bisa keputus di tengah dan datanya jadi tidak konsisten. 30 detik kasih ruang lebih supaya `queue:work` bisa menyelesaikan job yang sedang berjalan dengan aman sebelum container benar-benar dimatikan.

## Kenapa `--queue=default` Eksplisit?

Sekarang cuma ada satu antrian (`default`), tapi ditulis eksplisit dari awal supaya gampang diperluas nanti — misal `--queue=payroll,notifications,default` kalau modul Payroll (STEP 54+) butuh prioritas lebih tinggi dibanding job notifikasi biasa.

## Status Saat Ini (STEP 8)

Container `queue` akan menampilkan pesan "backend belum di-install" dan idle (`tail -f /dev/null`) sampai STEP 11 (Laravel Installation) selesai dan file `artisan` benar-benar ada di `backend/`.
