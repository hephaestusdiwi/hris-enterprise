<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'attendance.device' => \App\Modules\Attendance\Middleware\EnsureAttendanceDeviceToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Semua route project ini didaftarkan di bawah prefix /api (lihat
        // withRouting() di atas). Tanpa ini, Laravel nentuin JSON vs redirect
        // berdasarkan header Accept request — request yang WAJIB multipart
        // (file upload, tidak bisa pakai postJson()) jadi kena redirect 302
        // waktu validasi gagal, bukan 422 JSON. Ini pattern bawaan Laravel 11
        // buat API-only app, bukan custom exception handler baru.
        $exceptions->shouldRenderJsonWhen(function ($request, \Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
