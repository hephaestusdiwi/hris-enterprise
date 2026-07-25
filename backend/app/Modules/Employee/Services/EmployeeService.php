<?php

namespace App\Modules\Employee\Services;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeService
{
    /**
     * Buat Employee baru sekaligus User account-nya (Architecture Decision:
     * Employee WAJIB punya User — lihat docs/EMPLOYEE-USER-ACCOUNT.md).
     *
     * Menerima salah satu dari dua mode (sudah divalidasi StoreEmployeeRequest):
     * - "user_id": link ke User yang sudah ada
     * - "new_user": ['email' => ..., 'password' => ?...] auto-provision User baru
     *
     * @return array{employee: Employee, generated_password: ?string}
     */
    public function createWithUserAccount(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $generatedPassword = null;

            if (! empty($data['new_user']['email'])) {
                $plainPassword = $data['new_user']['password'] ?? Str::password(12, symbols: false);

                $user = User::create([
                    'name' => trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    'email' => $data['new_user']['email'],
                    'password' => bcrypt($plainPassword),
                ]);

                $user->assignRole('employee');

                $data['user_id'] = $user->id;

                // Cuma dikembalikan ke response kalau password-nya kita yang
                // generate — kalau HR yang set manual, HR sudah tahu passwordnya.
                if (empty($data['new_user']['password'])) {
                    $generatedPassword = $plainPassword;
                }
            }

            unset($data['new_user']);

            $employee = Employee::create($data);

            return [
                'employee' => $employee,
                'generated_password' => $generatedPassword,
            ];
        });
    }
}
