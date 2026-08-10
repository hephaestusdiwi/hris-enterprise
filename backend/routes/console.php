<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('working-schedule:apply-scheduled-changes')
    ->dailyAt('00:05');

// Effective-dated Employee Movement (Phase 2) — apply movement yang approved
// tapi effective_date-nya baru tiba hari ini.
Schedule::command('employee-movements:apply-due')
    ->dailyAt('00:15');

// Batch tahunan — generate balance seluruh employee + proses carry over di awal periode baru
Schedule::command('leave-balance:sync')->yearlyOn(1, 1, '00:10');

// Safety net harian — nangkep employee yang baru eligible di tengah tahun murni karena
// waktu berjalan (mis. baru genap min_service_months), yang gak ke-trigger oleh event Employee.
Schedule::command('leave-balance:sync')->dailyAt('01:00');