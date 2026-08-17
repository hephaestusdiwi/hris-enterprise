<?php

namespace App\Modules\Announcement\Services;

use App\Modules\Announcement\Enums\AnnouncementStatus;
use App\Modules\Announcement\Enums\AnnouncementTargetType;
use App\Modules\Announcement\Exceptions\AnnouncementException;
use App\Modules\Announcement\Models\Announcement;
use App\Modules\Announcement\Models\AnnouncementRecipient;
use App\Modules\Announcement\Models\AnnouncementTarget;
use App\Modules\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pemilik business rule Announcement: create/update (cuma boleh saat draft),
 * publish (resolve target -> recipient, transaction-safe & idempotent),
 * markAsRead. Attachment upload ditangani di Controller (mengikuti pola
 * EmployeePhotoController — Storage::disk('public')), Service cuma
 * nyimpen record-nya lewat method attach().
 */
class AnnouncementService
{
    /**
     * @param array{title: string, content: string, announcement_category_id: int, target_type: string, targets?: array<int, array{type: string, id: int}>} $data
     */
    public function create(array $data, User $creator): Announcement
    {
        return DB::transaction(function () use ($data, $creator) {
            $announcement = Announcement::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'announcement_category_id' => $data['announcement_category_id'],
                'target_type' => $data['target_type'],
                'status' => AnnouncementStatus::Draft->value,
                'created_by_user_id' => $creator->id,
            ]);

            $this->syncTargets($announcement, $data['targets'] ?? []);

            return $announcement->fresh(['targets']);
        });
    }

    /**
     * SENGAJA cuma bisa update selagi status masih Draft — begitu Published,
     * recipient sudah ke-resolve, ubah target/content sesudahnya butuh
     * business rule tambahan (mis. notifikasi ulang) yang di luar scope
     * Phase 1 ini.
     */
    public function update(Announcement $announcement, array $data): Announcement
    {
        $this->assertIsDraft($announcement);

        return DB::transaction(function () use ($announcement, $data) {
            $announcement->update([
                'title' => $data['title'],
                'content' => $data['content'],
                'announcement_category_id' => $data['announcement_category_id'],
                'target_type' => $data['target_type'],
            ]);

            if (array_key_exists('targets', $data)) {
                $this->syncTargets($announcement, $data['targets'] ?? []);
            }

            return $announcement->fresh(['targets']);
        });
    }

    /**
     * Dipakai juga oleh AnnouncementAttachmentController — begitu Published,
     * announcement LOCKED TOTAL, bukan cuma title/content/target tapi juga
     * attachment (upload maupun delete). Satu titik enforcement, bukan
     * duplikasi pengecekan status di tiap Controller.
     */
    public function assertIsDraft(Announcement $announcement): void
    {
        if ($announcement->status !== AnnouncementStatus::Draft) {
            throw new AnnouncementException('Announcement yang sudah Published tidak bisa diubah (termasuk attachment).');
        }
    }

    /**
     * Idempotent: kalau sudah Published sebelumnya, langsung return tanpa
     * proses ulang — jaminan utama "jangan bikin recipient duplicate" dan
     * "publish harus idempotent" sekaligus, paling sederhana dan paling
     * jujur (bukan re-resolve diam-diam yang bisa nambah/ngurangin recipient
     * tanpa disadari admin).
     */
    public function publish(Announcement $announcement): Announcement
    {
        if ($announcement->status === AnnouncementStatus::Published) {
            return $announcement;
        }

        return DB::transaction(function () use ($announcement) {
            $employeeIds = $this->resolveTargetEmployeeIds($announcement);

            $now = now();
            $rows = array_map(fn (int $employeeId) => [
                'announcement_id' => $announcement->id,
                'employee_id' => $employeeId,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $employeeIds);

            if (! empty($rows)) {
                // upsert dengan unique(announcement_id, employee_id) sebagai
                // constraint — aman dipanggil berkali-kali, tidak akan
                // duplicate baris ataupun menimpa read_at yang sudah keisi.
                AnnouncementRecipient::upsert($rows, ['announcement_id', 'employee_id'], ['updated_at']);
            }

            $announcement->update([
                'status' => AnnouncementStatus::Published->value,
                'published_at' => $now,
            ]);

            return $announcement->fresh(['recipients']);
        });
    }

    /**
     * @return array<int, int> employee_id yang jadi target
     */
    public function resolveTargetEmployeeIds(Announcement $announcement): array
    {
        $query = Employee::query()->whereNull('resign_date');

        if ($announcement->target_type === AnnouncementTargetType::All) {
            return $query->pluck('id')->all();
        }

        $targets = $announcement->targets()->get()->groupBy('target_type');

        if ($targets->isEmpty()) {
            return [];
        }

        // OR di semua kriteria (across dimensi maupun antar value dalam
        // dimensi yang sama) — employee match kalau match SALAH SATU baris
        // target manapun. Ini interpretasi paling umum untuk "distribution
        // list" semacam ini; didokumentasikan sebagai inference di report,
        // bukan behavior Talenta yang terverifikasi presisi.
        $query->where(function ($q) use ($targets) {
            foreach ($targets as $criteriaType => $rows) {
                $column = \App\Modules\Announcement\Enums\AnnouncementTargetCriteriaType::from($criteriaType)->employeeColumn();
                $ids = $rows->pluck('target_id')->all();
                $q->orWhereIn($column, $ids);
            }
        });

        return $query->pluck('id')->all();
    }

    public function markAsRead(Announcement $announcement, Employee $employee): void
    {
        AnnouncementRecipient::where('announcement_id', $announcement->id)
            ->where('employee_id', $employee->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @param array<int, array{type: string, id: int}> $targets
     */
    private function syncTargets(Announcement $announcement, array $targets): void
    {
        $announcement->targets()->delete();

        foreach ($targets as $target) {
            AnnouncementTarget::create([
                'announcement_id' => $announcement->id,
                'target_type' => $target['type'],
                'target_id' => $target['id'],
            ]);
        }
    }
}
