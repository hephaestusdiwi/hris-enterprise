<?php

namespace App\Modules\Employee\Contracts;

use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Reusable organizational hierarchy — dipakai Policy/Scope Employee sendiri
 * SEKARANG, dan module lain (Attendance, Leave, Tasks, Timesheet,
 * Performance, dst) NANTINYA lewat interface ini.
 *
 * PENTING — batas tanggung jawab:
 * - Ini cuma soal POPULATION/SCOPE ("siapa saja yang ada di bawah/atas siapa").
 * - BUKAN permission ("boleh ngapain") — itu tetap Spatie Permission.
 * - BUKAN approval line ("siapa approver step ini") — itu tetap
 *   ApprovalStepApproverResolver + ApprovalFlow, yang sengaja cuma pakai
 *   direct manager (1 level), bukan seluruh chain.
 */
interface EmployeeHierarchyServiceInterface
{
    /**
     * Bawahan LANGSUNG (1 level).
     *
     * @return Collection<int, Employee>
     */
    public function directReports(Employee $manager): Collection;

    /**
     * Seluruh bawahan di semua level (subordinate tree / descendants).
     *
     * @return Collection<int, Employee>
     */
    public function descendants(Employee $manager): Collection;

    /**
     * Sama seperti descendants(), tapi cuma ID — buat query scoping
     * (whereIn) tanpa perlu hydrate model.
     *
     * @return array<int, int>
     */
    public function descendantIds(Employee $manager): array;

    /**
     * Rantai manager dari yang terdekat ke yang teratas (Supervisor,
     * Manager, Head, ...). Employee di index 0 = manager langsung.
     *
     * @return Collection<int, Employee>
     */
    public function managerChain(Employee $employee): Collection;

    /**
     * True kalau $target ada di subordinate tree $manager, di level manapun.
     * $manager terhadap dirinya sendiri = false (diri sendiri bukan subordinate).
     */
    public function isInSubordinateTree(Employee $manager, Employee $target): bool;

    /**
     * ID employee yang boleh dilihat $actor: dirinya sendiri + seluruh
     * descendant tree-nya. Dipakai Scope class buat whereIn().
     *
     * @return array<int, int>
     */
    public function visibleEmployeeIds(Employee $actor): array;
}
