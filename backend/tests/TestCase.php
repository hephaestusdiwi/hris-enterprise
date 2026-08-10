<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Override createApplication() (dipanggil dari TestCase::setUp() bawaan
     * Laravel, SEBELUM setUpTraits() yang men-trigger RefreshDatabase),
     * supaya koneksi database dibuktikan benar SEBELUM RefreshDatabase
     * sempat menyentuh migration apapun.
     *
     * Ini bukan sekadar asumsi phpunit.xml/config bekerja — ini pembuktian
     * runtime. Kalau karena alasan apapun (env var Docker yang immutable,
     * subprocess artisan, dsb.) koneksi yang ter-resolve BUKAN
     * 'pgsql_testing' dengan database 'hris_testing', seluruh test run
     * dihentikan PAKSA sebelum ada satu migration pun berjalan — supaya
     * database utama (hris) mustahil ke-reset oleh test.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->guardAgainstWrongTestDatabase();

        return $app;
    }

    private function guardAgainstWrongTestDatabase(): void
    {
        $expectedConnection = 'pgsql_testing';
        $expectedDatabase = 'hris_testing';

        $connectionName = config('database.default');
        $database = config("database.connections.{$connectionName}.database");

        if ($connectionName === $expectedConnection && $database === $expectedDatabase) {
            return;
        }

        fwrite(STDERR, sprintf(
            "\n\n!!! TEST DIHENTIKAN SEBELUM MIGRATION APAPUN BERJALAN !!!\n".
            "Koneksi DB aktif: '%s' -> database '%s'\n".
            "Yang seharusnya: '%s' -> database '%s'\n".
            "Database utama (hris) TIDAK disentuh. Cek phpunit.xml (DB_CONNECTION harus 'pgsql_testing')\n".
            "dan config/database.php (koneksi 'pgsql_testing' harus ada, database-nya hardcode 'hris_testing').\n\n",
            $connectionName,
            $database,
            $expectedConnection,
            $expectedDatabase,
        ));

        exit(1);
    }
}
