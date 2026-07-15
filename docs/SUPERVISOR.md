# Supervisor

## Kenapa Supervisor di Dalam Container `queue`, Bukan Cuma `restart: unless-stopped`?

`restart: unless-stopped` Docker cuma tahu satu hal: "proses utama container mati, restart seluruh container". Untuk kebutuhan lebih halus — jalanin beberapa worker paralel, restart cepat per-proses tanpa restart container penuh — itu ranahnya process manager seperti Supervisor. Ini pola yang sama yang sudah dipakai di deployment manual TB Store (Nginx + PHP-FPM + Supervisor di VPS).

## Kenapa `numprocs=2`?

Satu container `queue` sekarang menjalankan **2 worker paralel** (`queue-worker_00`, `queue-worker_01`) alih-alih 1. Kalau ada 2 job masuk bersamaan, keduanya diproses serentak, bukan antre satu-satu. Angka ini gampang dinaikkan nanti (`numprocs=4`, dst) begitu volume job HRIS sudah kelihatan pola bebannya — tanpa perlu ubah `docker-compose.yml` sama sekali, cukup edit `queue-worker.conf`.

## Kenapa `scheduler` TIDAK Dipindah ke Supervisor?

Scheduler secara desain cuma butuh **1 proses tunggal** (loop drift-corrected dari STEP 9) — nggak ada manfaat dari `numprocs` lebih dari 1 (malah bahaya: 2 scheduler jalan bersamaan bisa trigger job terjadwal dobel). Jadi `restart: unless-stopped` polos sudah cukup untuk `scheduler`.

## Kenapa Build Context Pindah dari `./docker/php` ke Root (`.`)?

Supaya `docker/php/Dockerfile` bisa `COPY` file dari folder tetangga (`docker/supervisor/`). Efek samping (positif): `.dockerignore` di root **baru sekarang beneran aktif** untuk build `php`/`queue`/`scheduler` — sebelumnya Docker nyari `.dockerignore` di `docker/php/` (context lama), yang nggak pernah ada, jadi exclude rule kita selama ini nggak pernah jalan untuk build tersebut.

## Status Saat Ini (STEP 10)

Sama seperti sebelumnya, worker akan nampilin "backend belum di-install" dan sleep berulang sampai STEP 11 (Laravel Installation) selesai.
