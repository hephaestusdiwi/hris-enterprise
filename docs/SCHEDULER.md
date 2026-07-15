# Scheduler

## Kenapa Loop `while true` + `sleep`, Bukan Cron Daemon Beneran?

Laravel secara resmi merekomendasikan satu baris crontab (`* * * * * php artisan schedule:run`) di production. Tapi di dalam container Docker yang minimal (Alpine, tanpa init system penuh), install & jalanin daemon `cron`/`crond` sebagai proses tambahan itu ribet untuk manfaat yang kita bisa dapetin lebih simpel: loop shell yang manggil `schedule:run` tiap ~60 detik. Laravel sendiri aman dipanggil berkali-kali dalam periode singkat — task yang belum "due" cuma di-skip, jadi nggak ada resiko job dobel-eksekusi.

Supervisor (STEP 10) nanti dipakai kalau di production butuh manage proses ini dengan auto-restart yang lebih canggih dari sekadar `restart: unless-stopped` Docker.

## Kenapa `sleep $(( 60 - $(date +%s) % 60 ))`, Bukan `sleep 60` Biasa?

`sleep 60` itung mundur **dari titik loop selesai jalan**, bukan dari batas menit sesungguhnya. Karena `schedule:run` butuh waktu proses yang nggak konstan (kadang cepat, kadang lambat kalau ada job berat), lama-lama waktu eksekusi bakal *drift* menjauh dari detik ke-0 tiap menit.

`$(date +%s) % 60` ngambil sisa detik dari epoch time sekarang dibagi 60 — hasilnya "sudah berapa detik dari batas menit terakhir". `60 - sisa itu` = "berapa detik lagi sampai batas menit berikutnya". Dengan ini, tiap iterasi loop selalu align ulang ke detik ke-0 tiap menit, nggak peduil berapa lama `schedule:run` sebelumnya makan waktu. Drift-nya jadi mendekati nol.

## Kenapa Log Diarahkan ke `storage/logs/scheduler.log`?

`docker compose logs scheduler` bagus buat debug real-time, tapi log itu bisa hilang kalau container di-recreate (`--force-recreate`) atau di-prune. Dengan output juga ditulis ke `storage/logs/scheduler.log` (folder yang sama dengan log Laravel lainnya), riwayat eksekusi scheduler tetap ada di filesystem `backend/` yang persist, dan bisa dibaca tools log monitoring yang sama dengan log aplikasi.

## Status Saat Ini (STEP 9)

Sama seperti `queue`, container `scheduler` masih nampilin "backend belum di-install" sampai STEP 11 (Laravel Installation) — karena `artisan` dan folder `storage/logs/` belum ada.
