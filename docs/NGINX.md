# Nginx

## Kenapa `nginx:1.27-alpine`?

Sejalan dengan pilihan base image lain di project ini (Alpine untuk ukuran kecil). Versi 1.27 adalah mainline stable saat step ini ditulis.

## Kenapa Config di-`COPY`, Bukan Bind Mount?

`default.conf` di-*bake* ke dalam image saat build (`COPY`), bukan bind mount seperti `backend/`/`frontend/`. Alasannya: config nginx jarang berubah dibanding source code aplikasi, dan dengan di-bake ke image, config yang jalan di production **pasti identik** dengan yang di-test saat build — tidak tergantung file di host yang bisa saja lupa di-commit atau berbeda antar environment.

## Alur Request

```
Browser -> nginx (:80, di-expose ke host lewat APP_PORT)
             │
             ├── static asset / route non-PHP -> served langsung dari /var/www/html/public
             │
             └── *.php -> fastcgi_pass ke php:9000 (container "php", STEP 5)
```

`root /var/www/html/public` mengarah ke folder `public/` Laravel — ini standar Laravel (entry point `index.php` ada di situ), belum ada isinya sampai STEP 11 (Laravel Installation).

## Kenapa `fastcgi_pass php:9000` (Bukan Unix Socket)?

Karena `nginx` dan `php` adalah container terpisah (beda filesystem), TCP lewat nama service Docker (`php:9000`) adalah cara standard untuk container saling komunikasi di custom bridge network — Unix socket butuh shared volume tambahan yang tidak perlu untuk kasus ini.

## Kenapa Ada Blok `deny` untuk Dotfile?

Mencegah file sensitif (`.env`, `.git/`, dll) ke-serve langsung lewat HTTP kalau suatu saat ke-taruh di dalam `public/` secara tidak sengaja — defense in depth, bukan pengganti dari memastikan file itu memang tidak ada di `public/`.

## Kenapa `client_max_body_size 100M`?

Disesuaikan dengan pola upload yang bakal ada di HRIS ini (foto profil karyawan, foto enroll wajah untuk attendance, dokumen pendukung recruitment/training) — nilai yang sama juga relevan dengan pengalaman upload gambar produk & video slider di TB Store.

## Security Header

`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy` ditambahkan sebagai baseline hardening minimal di level web server, terlepas dari header yang nanti mungkin juga diset di level aplikasi Laravel.

## Status Saat Ini (STEP 4)

Nginx sudah bisa **di-build dan divalidasi syntax-nya**, tapi request PHP akan gagal (502) sampai:
- **STEP 5** (`php` container dengan PHP-FPM) selesai, dan
- **STEP 11** (Laravel Installation) selesai, sehingga folder `backend/public` benar-benar ada.
