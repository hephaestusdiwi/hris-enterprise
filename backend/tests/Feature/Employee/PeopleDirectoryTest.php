<?php

namespace Tests\Feature\Employee;

use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * People Directory & Company Org Chart -- self-service, TANPA permission
 * gate. Guard krusial: employee role BIASA (tanpa 'view employees' sama
 * sekali) harus tetap bisa akses & melihat SELURUH company, bukan cuma
 * subordinate-nya sendiri (beda dari endpoint admin /employees/org-chart
 * yang lama).
 */
class PeopleDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_employee_can_see_all_active_coworkers_in_directory(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $viewer = Employee::factory()->create();
        $viewer->user->assignRole('employee');

        // Bukan atasan/bawahan viewer sama sekali -- orang random di company lain divisi.
        Employee::factory()->count(2)->create();
        Employee::factory()->create(['resign_date' => now()->subDay()]); // resigned, harus kefilter

        $response = $this->actingAs($viewer->user)->getJson('/api/people-directory');

        $response->assertOk();
        // viewer sendiri + 2 orang lain = 3, yang resign gak ikut.
        $response->assertJsonCount(3, 'data');
    }

    public function test_plain_employee_can_view_a_coworkers_directory_detail(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $viewer = Employee::factory()->create();
        $viewer->user->assignRole('employee');
        $coworker = Employee::factory()->create();

        $response = $this->actingAs($viewer->user)->getJson("/api/people-directory/{$coworker->id}");

        $response->assertOk();
        $this->assertSame($coworker->id, $response->json('data.id'));
    }

    public function test_resigned_employee_returns_404_on_directory_detail(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $viewer = Employee::factory()->create();
        $viewer->user->assignRole('employee');
        $resigned = Employee::factory()->create(['resign_date' => now()->subDay()]);

        $this->actingAs($viewer->user)
            ->getJson("/api/people-directory/{$resigned->id}")
            ->assertNotFound();
    }

    public function test_plain_employee_can_see_full_company_org_chart_not_just_own_subtree(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $viewer = Employee::factory()->create();
        $viewer->user->assignRole('employee');

        // Manager terpisah dengan bawahan lain -- SAMA SEKALI di luar tree viewer.
        $otherManager = Employee::factory()->create();
        Employee::factory()->create(['manager_employee_id' => $otherManager->id]);

        $response = $this->actingAs($viewer->user)->getJson('/api/company-org-chart');

        $response->assertOk();
        // Root-level nodes: viewer + otherManager (dua-duanya gak punya manager),
        // artinya viewer bisa lihat cabang company yang bukan miliknya.
        $rootIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($rootIds->contains($otherManager->id));
    }
}