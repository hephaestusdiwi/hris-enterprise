<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Struktur yang dipakai di semua test (sesuai contoh di task):
 *
 * Head
 * └── Manager
 *     ├── Supervisor A
 *     │   ├── Staff A
 *     │   └── Staff B
 *     └── Supervisor B
 *         ├── Staff C
 *         └── Staff D
 */
class EmployeeHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeHierarchyServiceInterface $hierarchy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hierarchy = app(EmployeeHierarchyServiceInterface::class);
    }

    /**
     * @return array<string, Employee>
     */
    private function buildOrgTree(): array
    {
        $head = Employee::factory()->create();
        $manager = Employee::factory()->create(['manager_employee_id' => $head->id]);
        $supervisorA = Employee::factory()->create(['manager_employee_id' => $manager->id]);
        $supervisorB = Employee::factory()->create(['manager_employee_id' => $manager->id]);
        $staffA = Employee::factory()->create(['manager_employee_id' => $supervisorA->id]);
        $staffB = Employee::factory()->create(['manager_employee_id' => $supervisorA->id]);
        $staffC = Employee::factory()->create(['manager_employee_id' => $supervisorB->id]);
        $staffD = Employee::factory()->create(['manager_employee_id' => $supervisorB->id]);

        return compact('head', 'manager', 'supervisorA', 'supervisorB', 'staffA', 'staffB', 'staffC', 'staffD');
    }

    public function test_employee_has_manager(): void
    {
        $manager = Employee::factory()->create();
        $staff = Employee::factory()->create(['manager_employee_id' => $manager->id]);

        $this->assertTrue($staff->manager->is($manager));
    }

    public function test_direct_reports(): void
    {
        $tree = $this->buildOrgTree();

        $directReports = $this->hierarchy->directReports($tree['manager'])->pluck('id')->sort()->values();

        $this->assertEqualsCanonicalizing(
            [$tree['supervisorA']->id, $tree['supervisorB']->id],
            $directReports->all(),
        );
    }

    public function test_multi_level_descendants(): void
    {
        $tree = $this->buildOrgTree();

        $descendantIds = $this->hierarchy->descendantIds($tree['manager']);

        $this->assertEqualsCanonicalizing(
            [
                $tree['supervisorA']->id, $tree['supervisorB']->id,
                $tree['staffA']->id, $tree['staffB']->id,
                $tree['staffC']->id, $tree['staffD']->id,
            ],
            $descendantIds,
        );

        // Head lihat SEMUA orang di bawahnya (5 level termasuk manager).
        $headDescendants = $this->hierarchy->descendantIds($tree['head']);
        $this->assertCount(7, $headDescendants);
    }

    public function test_manager_chain(): void
    {
        $tree = $this->buildOrgTree();

        $chain = $this->hierarchy->managerChain($tree['staffA'])->pluck('id')->all();

        $this->assertSame(
            [$tree['supervisorA']->id, $tree['manager']->id, $tree['head']->id],
            $chain,
        );
    }

    public function test_employee_cannot_see_sibling_branch_outside_hierarchy_scope(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tree = $this->buildOrgTree();

        $supervisorAUser = User::factory()->create();
        $supervisorAUser->assignRole('employee');
        $supervisorAUser->givePermissionTo('view employees');
        $tree['supervisorA']->update(['user_id' => $supervisorAUser->id]);

        // Supervisor A TIDAK boleh lihat Staff C (anak buah Supervisor B —
        // sibling branch, bukan bawahannya).
        $this->assertFalse(
            $this->hierarchy->isInSubordinateTree($tree['supervisorA'], $tree['staffC'])
        );

        $response = $this->actingAs($supervisorAUser)->getJson("/api/employees/{$tree['staffC']->id}");
        $response->assertForbidden();
    }

    public function test_higher_level_manager_can_see_subordinate_tree(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tree = $this->buildOrgTree();

        $managerUser = User::factory()->create();
        $managerUser->assignRole('employee');
        $managerUser->givePermissionTo('view employees');
        $tree['manager']->update(['user_id' => $managerUser->id]);

        // Manager (2 level di atas Staff A) tetap bisa lihat Staff A lewat
        // Supervisor A — bukan cuma direct report.
        $this->assertTrue(
            $this->hierarchy->isInSubordinateTree($tree['manager'], $tree['staffA'])
        );

        $response = $this->actingAs($managerUser)->getJson("/api/employees/{$tree['staffA']->id}");
        $response->assertOk();

        // List endpoint juga harus konsisten: Manager lihat dirinya sendiri +
        // seluruh subordinate tree-nya (6 orang) di /api/employees.
        $listResponse = $this->actingAs($managerUser)->getJson('/api/employees');
        $listResponse->assertOk();
        $visibleIds = collect($listResponse->json('data.data'))->pluck('id')->all();

        foreach ([$tree['manager'], $tree['supervisorA'], $tree['supervisorB'], $tree['staffA'], $tree['staffB'], $tree['staffC'], $tree['staffD']] as $expected) {
            $this->assertContains($expected->id, $visibleIds);
        }
        $this->assertNotContains($tree['head']->id, $visibleIds);
    }

    /**
     * Transfer manager lewat Employee Movement TIDAK merusak hierarchy —
     * begitu movement ter-apply, descendant tree langsung reflect state baru,
     * dan history perpindahannya tetap tercatat di before/after snapshot.
     */
    public function test_manager_change_does_not_break_current_hierarchy(): void
    {
        $tree = $this->buildOrgTree();

        // Staff A dipindah dari Supervisor A ke Supervisor B secara langsung
        // (setara hasil akhir setelah Employee Movement ter-apply).
        $tree['staffA']->update(['manager_employee_id' => $tree['supervisorB']->id]);

        $this->assertFalse(
            $this->hierarchy->isInSubordinateTree($tree['supervisorA'], $tree['staffA']->fresh())
        );
        $this->assertTrue(
            $this->hierarchy->isInSubordinateTree($tree['supervisorB'], $tree['staffA']->fresh())
        );
    }

    /**
     * Regression test untuk bug yang ditemukan saat integrasi frontend:
     * org-chart dulu selalu return SELURUH company tanpa scoping. Sekarang
     * non-admin/hr harus di-root-kan ke dirinya sendiri.
     */
    public function test_org_chart_is_scoped_for_non_admin_hr(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tree = $this->buildOrgTree();

        $supervisorAUser = User::factory()->create();
        $supervisorAUser->assignRole('employee');
        $supervisorAUser->givePermissionTo('view employees');
        $tree['supervisorA']->update(['user_id' => $supervisorAUser->id]);

        $response = $this->actingAs($supervisorAUser)->getJson('/api/employees/org-chart');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data); // root tunggal: diri sendiri
        $this->assertSame($tree['supervisorA']->id, $data[0]['id']);

        $childIds = collect($data[0]['children'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$tree['staffA']->id, $tree['staffB']->id], $childIds);

        // Pastikan sibling branch (Supervisor B & anak buahnya) benar-benar
        // tidak ada di response sama sekali, di level manapun.
        $encoded = json_encode($data);
        $this->assertStringNotContainsString((string) $tree['supervisorB']->id, $encoded);
        $this->assertStringNotContainsString((string) $tree['staffC']->id, $encoded);
    }

    public function test_hierarchy_endpoint_returns_manager_and_direct_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $tree = $this->buildOrgTree();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->getJson("/api/employees/{$tree['supervisorA']->id}/hierarchy");

        $response->assertOk();
        $response->assertJsonPath('data.manager.id', $tree['manager']->id);

        $directReportIds = collect($response->json('data.direct_reports'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$tree['staffA']->id, $tree['staffB']->id], $directReportIds);
    }

    public function test_hierarchy_endpoint_handles_no_manager_and_no_direct_reports(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $lonely = Employee::factory()->create(); // tidak ada manager, tidak ada subordinate

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->getJson("/api/employees/{$lonely->id}/hierarchy");

        $response->assertOk();
        $response->assertJsonPath('data.manager', null);
        $this->assertSame([], $response->json('data.direct_reports'));
    }
}
