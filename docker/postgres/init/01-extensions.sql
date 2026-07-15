-- Extension yang dipakai project ini (lihat docs/ARCHITECTURE.md).
-- File di /docker-entrypoint-initdb.d/ cuma jalan otomatis SEKALI, pas data
-- directory postgres masih kosong (first init container). Kalau ada tambahan
-- extension nanti setelah container sudah pernah jalan, harus lewat migration
-- Laravel (STEP 11+), bukan edit file ini.

-- pg_trgm  : trigram similarity — dasar buat pencarian nama karyawan/company
--            yang typo-tolerant (ILIKE biasa nggak kena index tanpa ini).
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- unaccent : pencarian nggak sensitif diakritik. Jarang kepake buat Bahasa
--            Indonesia, tapi murah buat diaktifkan dari awal daripada nambah
--            migration terpisah nanti.
CREATE EXTENSION IF NOT EXISTS unaccent;

-- Catatan: gen_random_uuid() (dipakai untuk UUID di tabel Employee, Company,
-- dst — lihat ARCHITECTURE.md) sudah built-in di PostgreSQL 13+, jadi
-- extension pgcrypto SENGAJA tidak diaktifkan di sini.
