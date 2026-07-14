# HRIS Enterprise

Sistem HRIS (Human Resource Information System) enterprise-grade, dibangun dari nol dengan arsitektur **Modular Monolith** yang dirancang agar setiap module dapat dipisah menjadi microservice di kemudian hari tanpa refactor besar.

Target setara dengan sistem seperti Mekari Talenta, dibangun bertahap per STEP mengikuti roadmap di [`docs/ROADMAP.md`](docs/ROADMAP.md).

## Tech Stack

### Backend
- Laravel 12 (PHP 8.3)
- PostgreSQL 17
- Redis
- Laravel Sanctum (Authentication)
- Spatie Laravel Permission (Authorization / RBAC)
- Laravel Queue
- Laravel Scheduler

### Frontend
- Vue 3 (Composition API)
- TypeScript
- Vite
- Pinia
- Vue Router
- Tailwind CSS v4
- Axios

### DevOps
- Docker & Docker Compose
- Nginx
- Supervisor

## Struktur Repository

```
hris-enterprise/
├── backend/            # Laravel 12 API (Modular Monolith)
├── frontend/           # Vue 3 + TypeScript SPA
├── docker/             # Dockerfile & konfigurasi service (nginx, php-fpm, supervisor, dll)
├── docs/               # Dokumentasi arsitektur & roadmap
├── .env.example
├── .gitignore
└── README.md
```

> Catatan: `backend/` dan `frontend/` masih kosong di STEP 1. Instalasi Laravel dan Vue dilakukan di STEP 11 dan STEP 12 sesuai roadmap. `docker/` akan diisi mulai STEP 2.

## Arsitektur

Lihat penjelasan lengkap di [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

Ringkasnya:
- **Modular Monolith**, bukan microservices — satu deployable unit, tapi setiap domain bisnis (Employee, Attendance, Payroll, Leave, dll) diisolasi sebagai module independen di `app/Modules/*`.
- Setiap module memiliki struktur internal sendiri: Controllers, Models, Services, Repositories, Policies, Requests, Resources, Events, Listeners, Jobs, Routes, Tests.
- Business logic tidak boleh berada di Controller — selalu lewat Service Layer.
- API response format konsisten: `{ "success": bool, "message": string, "data": {} }`.

## Progress

Project dibangun bertahap. Status pengerjaan step per step dicatat di [`docs/ROADMAP.md`](docs/ROADMAP.md).

| Step | Nama | Status |
|------|------|--------|
| 1 | Setup Project | ✅ Selesai |
| 2 | Docker | ⬜ Belum |
| 3 | Docker Compose | ⬜ Belum |
| ... | ... | ⬜ Belum |

## Cara Menjalankan

Belum tersedia — environment Docker baru dikerjakan di STEP 2 dan seterusnya.

## Lisensi

Proprietary — internal project.
