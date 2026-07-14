# Docker Compose

## Service yang Didefinisikan

Sesuai container minimal yang ditentukan di awal: `nginx`, `php` (php-fpm), `node`, `postgres`, `redis`, `queue`, `scheduler`.

| Service | Image / Build | Fungsi |
|---|---|---|
| `php` | build dari `docker/php` | PHP-FPM 8.3, jalanin aplikasi Laravel |
| `nginx` | build dari `docker/nginx` | Web server, reverse proxy ke `php`, entry point HTTP |
| `postgres` | `postgres:17-alpine` (official image) | Database utama |
| `redis` | `redis:7-alpine` (official image) | Cache, session, queue driver |
| `node` | `node:22-alpine` (official image) | Dev server Vite untuk frontend (hot reload) |
| `queue` | build dari `docker/php` (image sama dengan `php`) | Worker `php artisan queue:work` |
| `scheduler` | build dari `docker/php` (image sama dengan `php`) | Loop `php artisan schedule:run` tiap 60 detik |

## Kenapa `queue` dan `scheduler` Container Terpisah dari `php`?

Mengikuti prinsip **satu proses utama per container**. Kalau digabung ke satu container `php` pakai Supervisor, satu container jadi bertanggung jawab atas 3 hal sekaligus (serve HTTP-FPM, queue worker, scheduler loop) — kalau salah satu crash atau butuh restart, yang lain ikut kena. Dengan container terpisah:
- Bisa restart queue worker tanpa ganggu request HTTP yang lagi jalan.
- Bisa scale worker queue secara independen (`docker compose up -d --scale queue=3`) kalau job menumpuk, tanpa nambah resource ke php-fpm.
- Log per proses jadi terpisah otomatis (`docker compose logs queue` vs `docker compose logs php`), lebih gampang di-debug dibanding grepping log Supervisor gabungan.

Supervisor (STEP 10 di roadmap) tetap akan dipakai, tapi perannya di **production single-container deployment** (STEP 96) sebagai opsi kalau nanti mau konsolidasi proses di VPS dengan resource sangat terbatas — bukan menggantikan setup compose dev ini.

## Kenapa `postgres` dan `redis` Pakai Official Image (Bukan Custom Build)?

Karena belum ada kebutuhan kustomisasi image di level ini (extension tambahan, dsb). Custom Dockerfile untuk `postgres/` dan `redis/` baru relevan kalau nanti butuh, misal, extension PostgreSQL tambahan atau Redis dengan modul khusus — sampai saat itu, override lewat environment variable & volume config saja (ditulis lengkap di STEP 6 & STEP 7).

## Kenapa Ada `healthcheck` di `postgres` dan `redis`?

Supaya service lain (`php`, `queue`, `scheduler`) yang `depends_on` mereka tidak langsung mulai sebelum database/cache benar-benar siap menerima koneksi — menghindari race condition "connection refused" saat container baru pertama kali `up`.

## Kenapa Network Kustom (`hris_network`), Bukan Default?

Docker Compose otomatis bikin network default per project, tapi kita definisikan eksplisit supaya:
- Nama network konsisten dan predictable kalau nanti perlu dihubungkan ke container lain di luar compose file ini (misal tooling debug).
- Jelas terlihat di `docker network ls` dan tidak tertukar dengan project Docker lain yang mungkin jalan di mesin yang sama.

## Kenapa Volume Postgres & Redis Named Volume, Bukan Bind Mount?

Data database sebaiknya dikelola Docker sendiri (`hris_postgres_data`, `hris_redis_data`) supaya permission dan performa I/O lebih baik dibanding bind mount ke folder Windows/WSL2. Source code (`backend/`, `frontend/`) sebaliknya pakai bind mount supaya perubahan file langsung kebaca container tanpa rebuild image — penting untuk development.

## Kondisi Saat Ini (STEP 3)

`docker/php/Dockerfile` dan `docker/nginx/Dockerfile` **belum ada** — baru ditulis di STEP 5 dan STEP 4. Jadi di STEP 3 ini, service `php`, `nginx`, `queue`, `scheduler` **belum bisa** di-build/jalan penuh. Yang sudah bisa dites sekarang:

- Validasi syntax: `docker compose config`
- Menjalankan service yang pakai official image: `docker compose up -d postgres redis`

Full `docker compose up` baru akan berhasil total setelah STEP 5 (PHP-FPM) dan STEP 4 (Nginx) selesai.
