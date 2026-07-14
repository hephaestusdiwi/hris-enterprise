# Docker

Lihat penjelasan lengkap strategi Docker di [`../docs/DOCKER.md`](../docs/DOCKER.md).

| Subfolder | Diisi di | Isi |
|---|---|---|
| `nginx/` | STEP 4 | Config nginx (reverse proxy ke php-fpm, serve frontend build) |
| `php/` | STEP 5 | Dockerfile PHP-FPM 8.3 + extensions + php.ini |
| `postgres/` | STEP 6 | Init script / config PostgreSQL 17 |
| `redis/` | STEP 7 | Config Redis |
| `supervisor/` | STEP 9 & 10 | Config Supervisor untuk queue worker & scheduler |

`docker-compose.yml` yang menyatukan semua service ini akan dibuat di **STEP 3 — Docker Compose**.
