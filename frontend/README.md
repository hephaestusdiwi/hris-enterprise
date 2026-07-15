# HRIS Enterprise — Frontend

Vue 3 SPA (Composition API) dengan Vite, Pinia untuk state management, dan Vue Router untuk routing. TypeScript akan ditambahkan di **STEP 13**, Tailwind CSS v4 di **STEP 14**.

## Tech Stack

- Vue 3 (Composition API)
- Vite
- Pinia
- Vue Router
- Axios
- ESLint + Prettier + oxlint

## Struktur

Mengikuti struktur default `create-vue`, dengan konvensi tambahan:
- `src/stores/` — Pinia stores per domain
- `src/services/` — wrapper axios per module API
- `src/views/` — halaman routed
- `src/components/` — komponen reusable

Lihat `docs/ARCHITECTURE.md` dan `docs/ROADMAP.md` di root project untuk detail lengkap.
