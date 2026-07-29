<?php

namespace App\Modules\Holiday\DataTransferObjects;

/**
 * Bentuk data netral hari libur nasional, terlepas dari bentuk respons
 * JSON provider aslinya. Setiap provider WAJIB memetakan responsnya ke DTO ini.
 */
class NationalHolidayData
{
    public function __construct(
        public readonly string $date,       // format Y-m-d
        public readonly string $name,
        public readonly string $externalId, // contoh: "nager:ID:2026-01-01"
        public readonly string $type = 'national',
    ) {
    }
}