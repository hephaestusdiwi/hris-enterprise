# Docker

## Kenapa Docker?

Supaya environment development, staging, dan production **identik** — menghindari masalah klasik "di laptop saya jalan, di server nggak". Setiap service (nginx, php-fpm, postgres, redis, queue worker, scheduler) berjalan sebagai container terisolasi, didefinisikan sekali di `docker-compose.yml` (STEP 3), dan bisa dijalankan siapa saja tanpa install PHP/Postgres/Redis manual di mesin masing-masing.

Ini beda dengan setup TB Store yang deploy manual di VPS (Nginx + PHP-FPM + MariaDB terinstall langsung di OS). Untuk HRIS, semua service di-containerize dari awal supaya:
- Versi PHP/Postgres/Redis konsisten antara dev (Windows/WSL2) dan production (VPS Ubuntu).
- Onboarding developer baru tinggal `docker compose up`, tanpa setup manual berjam-jam.
- Mudah di-scale per service kalau nanti volumenya naik (misal PHP-FPM container di-scale terpisah dari queue worker).

## Struktur Folder Docker

```
docker/
├── nginx/          # config nginx (default.conf, dll) -> diisi STEP 4
├── php/             # Dockerfile PHP-FPM + php.ini custom -> diisi STEP 5
├── postgres/        # init script / config Postgres -> diisi STEP 6
├── redis/           # config Redis -> diisi STEP 7
└── supervisor/       # config Supervisor untuk queue worker & scheduler -> diisi STEP 9 & 10
```

Setiap subfolder sengaja dikosongkan dulu di STEP 2 — isinya ditulis di step masing-masing supaya setiap STEP tetap independen dan mudah di-review satu-satu, sesuai aturan project ini.

## Strategi Image

- **Base image PHP**: `php:8.3-fpm-alpine` — dipilih Alpine untuk image size kecil, cocok untuk VPS dengan resource terbatas (mengacu ke pengalaman VPS Natanetwork yang sudah dipakai TB Store, single-core/RAM terbatas).
- **Multi-stage build** dipakai khusus di image frontend (Node build stage → hasil `dist/` di-copy ke nginx) supaya image final tidak membawa `node_modules` dan build tools yang tidak perlu di production.
- **Named volume** dipakai untuk data Postgres & Redis (persist antar restart container), **bind mount** dipakai untuk source code saat development (biar hot-reload tanpa rebuild image).
- Semua container terhubung lewat **custom bridge network** (didefinisikan di STEP 3), bukan default network, supaya service saling mengenali lewat nama container (misal `php` bisa connect ke `postgres:5432`) dan supaya tidak bentrok dengan container project lain (TB Store, mindwayPOS) yang mungkin jalan di mesin/VPS yang sama.

## Prasyarat di Mesin Development

Karena kamu kerja di Windows dengan WSL2 Ubuntu:

1. Install **Docker Desktop for Windows**.
2. Saat instalasi, aktifkan **"Use WSL 2 based engine"**.
3. Di Docker Desktop → Settings → Resources → WSL Integration → aktifkan integrasi untuk distro WSL2 Ubuntu kamu.
4. Jalankan semua command Docker dari dalam WSL2 Ubuntu (bukan dari Git Bash Windows), supaya performa filesystem lebih baik (bind mount dari WSL2 jauh lebih cepat dibanding dari `/mnt/c` atau `/mnt/g`).

Verifikasi instalasi (dijalankan dari WSL2 Ubuntu):

```bash
docker --version
docker compose version
```

## Prasyarat di VPS Production (nanti, STEP 14)

VPS Natanetwork (Ubuntu 22.04) yang selama ini dipakai untuk TB Store masih pakai instalasi manual (Nginx + PHP-FPM + MariaDB tanpa Docker). Untuk HRIS, direkomendasikan pakai **VPS terpisah** atau minimal **user/namespace Docker terpisah** di VPS yang sama, supaya container HRIS tidak bentrok port/service dengan setup manual TB Store. Detail deployment production dibahas di STEP 96 (Production Docker).
