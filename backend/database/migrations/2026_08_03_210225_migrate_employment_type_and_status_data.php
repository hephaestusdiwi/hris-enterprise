<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration — bukan sekadar perubahan schema.
 *
 * Sebelum migration ini, tabel `employment_statuses` sebenarnya menyimpan
 * Employment TYPE (Permanent/Contract/Probation/Intern), dan konsep
 * Employment STATUS (Active/Resigned/dst) belum benar-benar ada.
 *
 * Migration ini:
 * 1. Memastikan master data Employment Type & Employment Status yang benar tersedia.
 * 2. Memindahkan referensi lama employees.employment_status_id ke employees.employment_type_id.
 * 3. Menentukan employment_status_id berdasarkan resign_date.
 * 4. Menghapus data lama yang sebenarnya merupakan Employment Type.
 */
return new class extends Migration
{
    private const TYPES = [
        ['name' => 'Permanent', 'code' => 'PERMANENT'],
        ['name' => 'Contract', 'code' => 'CONTRACT'],
        ['name' => 'Probation', 'code' => 'PROBATION'],
        ['name' => 'Intern', 'code' => 'INTERN'],
        ['name' => 'Freelance', 'code' => 'FREELANCE'],
        ['name' => 'Outsource', 'code' => 'OUTSOURCE'],
    ];

    private const STATUSES = [
        ['name' => 'Active', 'code' => 'ACTIVE'],
        ['name' => 'Inactive', 'code' => 'INACTIVE'],
        ['name' => 'Resigned', 'code' => 'RESIGNED'],
        ['name' => 'Terminated', 'code' => 'TERMINATED'],
        ['name' => 'Retired', 'code' => 'RETIRED'],
        ['name' => 'Suspended', 'code' => 'SUSPENDED'],
    ];

    private const OLD_STATUS_CODES_THAT_WERE_ACTUALLY_TYPES = [
        'PERMANENT',
        'CONTRACT',
        'PROBATION',
        'INTERN',
    ];

    public function up(): void
    {
        $now = now();

        DB::table('employment_types')->insertOrIgnore(array_map(
            fn ($type) => [...$type, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::TYPES,
        ));

        DB::table('employment_statuses')->insertOrIgnore(array_map(
            fn ($status) => [...$status, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::STATUSES,
        ));

        $typeIdByCode = DB::table('employment_types')->pluck('id', 'code');
        $statusIdByCode = DB::table('employment_statuses')->pluck('id', 'code');
        $oldStatusCodeById = DB::table('employment_statuses')->pluck('code', 'id');

        $activeStatusId = $statusIdByCode['ACTIVE'] ?? null;
        $resignedStatusId = $statusIdByCode['RESIGNED'] ?? null;

        DB::table('employees')
            ->select('id', 'employment_status_id', 'resign_date')
            ->orderBy('id')
            ->chunkById(200, function ($employees) use ($oldStatusCodeById, $typeIdByCode, $activeStatusId, $resignedStatusId) {
                foreach ($employees as $employee) {
                    $update = [];

                    $oldCode = $employee->employment_status_id
                        ? ($oldStatusCodeById[$employee->employment_status_id] ?? null)
                        : null;

                    if ($oldCode && isset($typeIdByCode[$oldCode])) {
                        $update['employment_type_id'] = $typeIdByCode[$oldCode];
                    }

                    $update['employment_status_id'] = $employee->resign_date
                        ? $resignedStatusId
                        : $activeStatusId;

                    if (! empty($update)) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update($update);
                    }
                }
            });

        DB::table('employment_statuses')
            ->whereIn('code', self::OLD_STATUS_CODES_THAT_WERE_ACTUALLY_TYPES)
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('employment_statuses')->insertOrIgnore(array_map(
            fn ($type) => [...$type, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::TYPES,
        ));

        $oldStatusIdByCode = DB::table('employment_statuses')->pluck('id', 'code');
        $typeCodeById = DB::table('employment_types')->pluck('code', 'id');

        DB::table('employees')
            ->select('id', 'employment_type_id')
            ->whereNotNull('employment_type_id')
            ->orderBy('id')
            ->chunkById(200, function ($employees) use ($typeCodeById, $oldStatusIdByCode) {
                foreach ($employees as $employee) {
                    $code = $typeCodeById[$employee->employment_type_id] ?? null;

                    if ($code && isset($oldStatusIdByCode[$code])) {
                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update([
                                'employment_status_id' => $oldStatusIdByCode[$code],
                            ]);
                    }
                }
            });

        DB::table('employment_statuses')
            ->whereIn('code', array_column(self::STATUSES, 'code'))
            ->delete();
    }
};