<?php

namespace App\Modules\EmployeeMovement\Enums;

enum EmployeeMovementType: string
{
    case Transfer = 'transfer';
    case Promotion = 'promotion';
    case Demotion = 'demotion';
    case ContractChange = 'contract_change';
    case ProbationConfirmed = 'probation_confirmed';
    case Resignation = 'resignation';
    case Rehire = 'rehire';

    /**
     * Field employees yang RELEVAN untuk tipe movement ini — dipakai untuk
     * membangun before/after snapshot. Bukan whitelist validasi (itu di
     * StoreEmployeeMovementRequest), cuma penentu field mana yang di-snapshot.
     *
     * @return array<int, string>
     */
    public function relevantFields(): array
    {
        return match ($this) {
            self::Transfer => ['company_id', 'branch_id', 'department_id', 'position_id', 'manager_employee_id'],
            self::Promotion, self::Demotion => ['job_level_id', 'position_id'],
            self::ContractChange => ['employment_type_id', 'contract_start_date', 'contract_end_date'],
            // Dipakai juga untuk aksi "Change Status" generik (bukan cuma
            // probation) — employment_status_id ditambahkan di sini karena
            // ini satu-satunya movement_type non-Resignation yang relevan
            // buat perubahan status tanpa embel-embel resign_date.
            self::ProbationConfirmed => ['probation_end_date', 'employment_type_id', 'employment_status_id'],
            self::Resignation => ['employment_status_id', 'resign_date'],
            self::Rehire => ['employment_status_id', 'resign_date', 'join_date'],
        };
    }
}
