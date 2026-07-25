<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillEmployeeUserAccounts extends Command
{
    protected $signature = 'employees:backfill-user-accounts';

    protected $description = 'Buat User account untuk Employee yang belum punya (persiapan sebelum employees.user_id jadi NOT NULL)';

    public function handle(): int
    {
        $employees = Employee::whereNull('user_id')->get();

        if ($employees->isEmpty()) {
            $this->info('Semua Employee sudah punya User account. Tidak ada yang perlu di-backfill.');

            return self::SUCCESS;
        }

        $this->info("Ditemukan {$employees->count()} Employee tanpa User account. Membuat akun...");

        $rows = [];

        DB::transaction(function () use ($employees, &$rows) {
            foreach ($employees as $employee) {
                $email = Str::lower($employee->employee_number).'@hris.local';

                // Jaga-jaga kalau ternyata email itu udah dipakai (edge case)
                if (User::where('email', $email)->exists()) {
                    $email = Str::lower($employee->employee_number).'-'.Str::random(4).'@hris.local';
                }

                $plainPassword = Str::password(12, symbols: false);

                $user = User::create([
                    'name' => trim($employee->first_name.' '.$employee->last_name),
                    'email' => $email,
                    'password' => bcrypt($plainPassword),
                ]);

                $user->assignRole('employee');

                $employee->update(['user_id' => $user->id]);

                $rows[] = [
                    $employee->employee_number,
                    $user->name,
                    $email,
                    $plainPassword,
                ];
            }
        });

        $this->newLine();
        $this->warn('CATAT PASSWORD INI SEKARANG — tidak akan ditampilkan lagi setelah ini:');
        $this->table(['Employee Number', 'Nama', 'Email', 'Password'], $rows);

        return self::SUCCESS;
    }
}
