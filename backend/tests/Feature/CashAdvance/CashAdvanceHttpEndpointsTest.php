<?php

namespace Tests\Feature\CashAdvance;

use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Models\CashAdvanceCategory;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Models\CashAdvanceRequest;
use App\Modules\CashAdvance\Services\CashAdvanceService;
use App\Modules\Employee\Models\Employee;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi HTTP untuk bug yang ditemukan di controller layer Cash Advance.
 *
 * CashAdvanceCoreFlowTest memanggil Service layer langsung, sehingga bug
 * pada controller/HTTP contract tidak ikut teruji. File ini secara khusus
 * memukul endpoint menggunakan actingAs()+HTTP.
 */
class CashAdvanceHttpEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function policyWithCategory(): array
    {
        $policy = CashAdvancePolicy::create([
            'name' => 'Business Trip',
            'effective_date' => now()->subYear()->toDateString(),
            'settlement_due_days' => 14,
            'is_active' => true,
        ]);

        $category = CashAdvanceCategory::create([
            'name' => 'Transport',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);

        $policy->categories()->attach($category->id);

        return [$policy, $category];
    }

    /**
     * Bug #1:
     *
     * CashAdvanceController::store() sebelumnya meneruskan User langsung
     * ke CashAdvanceService::submit(Employee $employee, ...), sehingga
     * terjadi TypeError.
     *
     * Endpoint harus resolve User -> Employee terlebih dahulu.
     */
    public function test_employee_can_submit_cash_advance_via_http_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);

        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $response = $this->actingAs($employee->user)
            ->postJson('/api/cash-advances', [
                'cash_advance_policy_id' => $policy->id,
                'purpose' => 'Perjalanan dinas Jakarta',
                'date_of_use' => '2027-02-01',
                'items' => [
                    [
                        'cash_advance_category_id' => $category->id,
                        'name' => 'Transport',
                        'amount' => '500000',
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);

        $this->assertSame(1, CashAdvanceRequest::count());

        $created = CashAdvanceRequest::first();

        $this->assertSame(
            $employee->id,
            $created->employee_id
        );

        $this->assertSame(
            CashAdvanceRequestStatus::Approved,
            $created->status,
            'Tanpa ApprovalFlow, submit lewat HTTP harus tetap auto-approve.'
        );
    }

    /**
     * Bug #2:
     *
     * CashAdvanceController::cancel() sebelumnya memanggil:
     *
     * cancel($cashAdvance, $request->user(), $reason)
     *
     * padahal signature service hanya:
     *
     * cancel(CashAdvanceRequest $request, string $reason)
     *
     * User yang nyempil menyebabkan argumen posisional salah.
     */
    public function test_employee_can_cancel_cash_advance_via_http_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);

        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $cashAdvance = app(CashAdvanceService::class)->submit($employee, [
            'cash_advance_policy_id' => $policy->id,
            'purpose' => 'Perjalanan dinas Bandung',
            'date_of_use' => '2027-02-01',
            'items' => [
                [
                    'cash_advance_category_id' => $category->id,
                    'name' => 'Transport',
                    'amount' => '300000',
                ],
            ],
        ]);

        $response = $this->actingAs($employee->user)
            ->postJson(
                "/api/cash-advances/{$cashAdvance->id}/cancel",
                [
                    'reason' => 'Rencana perjalanan batal',
                ]
            );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $cashAdvance->refresh();

        $this->assertSame(
            CashAdvanceRequestStatus::Cancelled,
            $cashAdvance->status
        );

        $this->assertSame(
            'Rencana perjalanan batal',
            $cashAdvance->cancel_reason
        );
    }

    /**
     * Bug #4:
     *
     * CashAdvanceController::disburse() harus meneruskan argument ke
     * CashAdvanceService::disburse() sesuai signature:
     *
     * disburse(
     *     CashAdvanceRequest $request,
     *     ?string $note,
     *     ?User $actor
     * )
     *
     * Endpoint disburse membutuhkan permission `disburse cash advances`,
     * sehingga actor HTTP menggunakan user dengan role finance.
     */
    public function test_finance_can_disburse_cash_advance_via_http_endpoint(): void
    {
        $this->seed(RolePermissionSeeder::class);

        [$policy, $category] = $this->policyWithCategory();

        // Employee yang mengajukan Cash Advance.
        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        // Finance yang melakukan disbursement.
        $disburser = Employee::factory()->create();
        $disburser->user->assignRole('hr');

        $cashAdvance = app(CashAdvanceService::class)->submit($employee, [
            'cash_advance_policy_id' => $policy->id,
            'purpose' => 'Perjalanan dinas Yogyakarta',
            'date_of_use' => '2027-02-01',
            'items' => [
                [
                    'cash_advance_category_id' => $category->id,
                    'name' => 'Transport',
                    'amount' => '400000',
                ],
            ],
        ]);

        // Tanpa ApprovalFlow, request harus auto-approved.
        $this->assertSame(
            CashAdvanceRequestStatus::Approved,
            $cashAdvance->status
        );

        $response = $this->actingAs($disburser->user)
            ->postJson(
                "/api/cash-advances/{$cashAdvance->id}/disburse",
                [
                    'disbursement_note' => 'Dicairkan melalui transfer bank.',
                ]
            );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $cashAdvance->refresh();

        $this->assertSame(
            CashAdvanceRequestStatus::NeedSettlement,
            $cashAdvance->status
        );

        $this->assertNotNull(
            $cashAdvance->disbursed_at
        );

        $this->assertSame(
            $disburser->user->id,
            $cashAdvance->disbursed_by_user_id
        );

        $this->assertSame(
            'Dicairkan melalui transfer bank.',
            $cashAdvance->disbursement_note
        );
    }

    /**
     * Bug #3:
     *
     * CashAdvanceAttachmentController sebelumnya membaca:
     *
     * $request->file('file')
     *
     * padahal Request memvalidasi:
     *
     * attachments[]
     *
     * Contract endpoint adalah multi-file.
     */
    public function test_employee_can_upload_multiple_attachments_via_http_endpoint(): void
    {
        Storage::fake('public');

        $this->seed(RolePermissionSeeder::class);

        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        $cashAdvance = app(CashAdvanceService::class)->submit($employee, [
            'cash_advance_policy_id' => $policy->id,
            'purpose' => 'Perjalanan dinas Surabaya',
            'date_of_use' => '2027-02-01',
            'items' => [
                [
                    'cash_advance_category_id' => $category->id,
                    'name' => 'Transport',
                    'amount' => '200000',
                ],
            ],
        ]);

        $response = $this->actingAs($employee->user)
            ->post(
                "/api/cash-advances/{$cashAdvance->id}/attachments",
                [
                    'attachments' => [
                        UploadedFile::fake()->create(
                            'bukti1.pdf',
                            200,
                            'application/pdf'
                        ),
                        UploadedFile::fake()->create(
                            'bukti2.jpg',
                            300,
                            'image/jpeg'
                        ),
                    ],
                ]
            );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data');

        $this->assertSame(
            2,
            $cashAdvance->fresh()->attachments()->count()
        );

        foreach ($cashAdvance->fresh()->attachments as $attachment) {
            Storage::disk('public')
                ->assertExists($attachment->file_path);
        }
    }
}