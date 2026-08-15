<?php

return [
    // Global untuk sekarang (belum per-company) — cukup untuk Phase 2.
    // Kalau nanti butuh per-company, ini pindah ke tabel settings company,
    // bukan nambah kolom baru di sini.
    'contract_reminder_days' => (int) env('CONTRACT_REMINDER_DAYS', 30),
    'probation_reminder_days' => (int) env('PROBATION_REMINDER_DAYS', 30),

    // Phase 3 — milestone reminder (H-30/H-14/H-7). Reminder cuma dikirim
    // waktu remaining_days PAS sama dengan salah satu angka ini (bukan
    // range) — itu mekanisme dedup-nya: scheduler jalan tiap hari, tapi
    // cuma 1 dari 365 hari yang match tiap milestone per employee.
    'reminder_milestones' => [30, 14, 7],
];
