<?php

namespace App\Modules\Employee\Services;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use App\Modules\LeaveBalance\Services\LeaveBalanceGenerationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeeService
{
    public function __construct(
        private LeaveBalanceGenerationService $leaveBalanceGenerationService,
        private EmployeeNumberGenerator $employeeNumberGenerator, // BARU
    ) {
    }

    /**
     * Buat Employee baru sekaligus User account-nya (Architecture Decision:
     * Employee WAJIB punya User — lihat docs/EMPLOYEE-USER-ACCOUNT.md).
     *
     * Menerima salah satu dari dua mode (sudah divalidasi StoreEmployeeRequest):
     * - "user_id": link ke User yang sudah ada (User sudah aktif, tidak ada invite)
     * - "new_user": ['email' => ...] auto-provision User baru dengan status
     *   pending_invite — password TIDAK di-generate di sini, employee wajib
     *   set password sendiri lewat invite link (lihat AccountActivationController).
     *
     * @return array{employee: Employee, invite_link: ?string}
     */
    public function createWithUserAccount(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $inviteLink = null;

            // BARU — tambahan 3 baris, tidak mengubah apapun di bawahnya.
            // employee_number selalu ada dari HTTP flow existing (StoreEmployeeRequest
            // mewajibkannya), jadi baris ini TIDAK PERNAH tereksekusi untuk flow itu —
            // cuma aktif untuk caller lain (Recruitment) yang tidak menyediakannya.
            if (empty($data['employee_number'])) {
                $data['employee_number'] = $this->employeeNumberGenerator->generate($data['company_id']);
            }

            if (! empty($data['new_user']['email'])) {
                [$user, $inviteLink] = $this->createPendingUser(
                    trim($data['first_name'].' '.($data['last_name'] ?? '')),
                    $data['new_user']['email'],
                );

                $data['user_id'] = $user->id;
            }

            unset($data['new_user']);

            $employee = Employee::create($data);

            $this->leaveBalanceGenerationService->generateForEmployee($employee, now());

            return ['employee' => $employee, 'invite_link' => $inviteLink];
        });
    }

    /**
     * Generate ulang invite link untuk User yang masih pending_invite
     * (link lama otomatis tidak berlaku begitu ini dipanggil).
     */
    public function resendInvite(Employee $employee): string
    {
        if (! $employee->user || ! $employee->user->isPendingInvite()) {
            throw new \RuntimeException('Employee ini tidak punya invite yang pending.');
        }

        [, $inviteLink] = $this->issueActivationToken($employee->user);

        return $inviteLink;
    }

    /**
     * @return array{0: User, 1: string} User yang baru dibuat + invite link
     */
    private function createPendingUser(string $name, string $email): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            // Password acak yang TIDAK PERNAH diberitahukan ke siapapun —
            // cuma placeholder karena kolom password NOT NULL. User wajib
            // set password sendiri lewat activation link.
            'password' => bcrypt(Str::random(40)),
            'account_status' => 'pending_invite',
        ]);

        $user->assignRole('employee');

        [$user, $inviteLink] = $this->issueActivationToken($user);

        return [$user, $inviteLink];
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function issueActivationToken(User $user): array
    {
        $plainToken = Str::random(48);

        $user->update([
            'invited_at' => now(),
            'activation_token_hash' => hash('sha256', $plainToken),
            'activation_token_expires_at' => now()->addDays(7),
        ]);

        $inviteLink = rtrim(config('app.frontend_url'), '/').'/activate-account?token='.$plainToken;

        return [$user, $inviteLink];
    }
}
