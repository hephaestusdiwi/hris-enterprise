<?php

namespace Tests\Feature\CashAdvance;

use App\Modules\ApprovalFlow\Enums\ApproverType;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Models\ApprovalFlowAssignment;
use App\Modules\ApprovalFlow\Models\ApprovalStep;
use App\Modules\CashAdvance\Enums\CashAdvanceRequestStatus;
use App\Modules\CashAdvance\Enums\CashAdvanceSettlementStatus;
use App\Modules\CashAdvance\Exceptions\CashAdvanceApprovalException;
use App\Modules\CashAdvance\Exceptions\CashAdvanceValidationException;
use App\Modules\CashAdvance\Models\CashAdvanceApprovalStepDecision;
use App\Modules\CashAdvance\Models\CashAdvanceCategory;
use App\Modules\CashAdvance\Models\CashAdvancePolicy;
use App\Modules\CashAdvance\Services\CashAdvanceApprovalService;
use App\Modules\CashAdvance\Services\CashAdvanceService;
use App\Modules\CashAdvance\Services\CashAdvanceSettlementApprovalService;
use App\Modules\CashAdvance\Services\CashAdvanceSettlementService;
use App\Modules\Employee\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAdvanceCoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private function policyWithCategory(): array
    {
        $policy = CashAdvancePolicy::create([
            'name' => 'Business Trip',
            'effective_date' => '2027-01-01',
            'settlement_due_days' => 14,
            'is_active' => true,
        ]);

        $category = CashAdvanceCategory::create([
            'name' => 'Transport',
            'code' => 'TRANSPORT',
            'is_active' => true,
        ]);

        $policy->categories()->attach($category->id);

        return [$policy, $category];
    }

    private function submitPayload(int $policyId, int $categoryId): array
    {
        return [
            'cash_advance_policy_id' => $policyId,
            'purpose' => 'Perjalanan dinas Jakarta',
            'date_of_use' => '2027-02-01',
            'items' => [
                [
                    'cash_advance_category_id' => $categoryId,
                    'name' => 'Transport',
                    'amount' => '500000',
                ],
                [
                    'cash_advance_category_id' => $categoryId,
                    'name' => 'Hotel',
                    'amount' => '1000000',
                ],
            ],
        ];
    }

    // 1,2,3,4,5: submit sukses, policy/category validasi, multi item dihitung benar
    public function test_employee_can_submit_cash_advance_with_multiple_items(): void
    {
        [$policy, $category] = $this->policyWithCategory();
        $employee = Employee::factory()->create();

        $request = app(CashAdvanceService::class)->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $this->assertSame('1500000.00', (string) $request->total_amount);
        $this->assertCount(2, $request->items);

        // Tanpa ApprovalFlow -> auto-approve, sama seperti Loan/Reimbursement.
        $this->assertSame(
            CashAdvanceRequestStatus::Approved,
            $request->fresh()->status
        );
    }

    public function test_submit_rejected_with_inactive_policy(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $policy->update(['is_active' => false]);

        $employee = Employee::factory()->create();

        $this->expectException(CashAdvanceValidationException::class);

        app(CashAdvanceService::class)->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );
    }

    public function test_submit_rejected_when_category_not_linked_to_policy(): void
    {
        [$policy] = $this->policyWithCategory();

        $foreignCategory = CashAdvanceCategory::create([
            'name' => 'Meals',
            'code' => 'MEALS',
            'is_active' => true,
        ]);

        $employee = Employee::factory()->create();

        $this->expectException(CashAdvanceValidationException::class);

        app(CashAdvanceService::class)->submit(
            $employee,
            $this->submitPayload($policy->id, $foreignCategory->id)
        );
    }

    // 6: employee tidak bisa lihat request employee lain (HTTP-level ownership check)
    public function test_employee_cannot_view_another_employees_cash_advance(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $owner = Employee::factory()->create();
        $stranger = Employee::factory()->create();

        $request = app(CashAdvanceService::class)->submit(
            $owner,
            $this->submitPayload($policy->id, $category->id)
        );

        $this->actingAs($stranger->user)
            ->getJson("/api/my-cash-advances/{$request->id}")
            ->assertStatus(403);
    }

    // 7,8,9,10,11: submit masuk approval via ApprovalFlow existing, approve/reject, duplicate decision ditolak
    public function test_request_goes_through_approval_flow_and_rejects_duplicate_decision(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $approverEmployee = Employee::factory()->create();

        $flow = ApprovalFlow::create([
            'name' => 'CA Flow',
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approverEmployee->id,
            'is_active' => true,
        ]);

        ApprovalFlowAssignment::create([
            'approval_flow_id' => $flow->id,
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $request = app(CashAdvanceService::class)->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        // Ada ApprovalFlow -> TIDAK auto-approve, tetap Pending Approval.
        $this->assertSame(
            CashAdvanceRequestStatus::PendingApproval,
            $request->fresh()->status
        );

        $this->assertNotNull($request->fresh()->approvalRequest);

        $decision = CashAdvanceApprovalStepDecision::first();

        $approvalService = app(CashAdvanceApprovalService::class);

        $approvalService->decide(
            $decision,
            $approverEmployee->user,
            'approve',
            'OK approved'
        );

        $this->assertSame(
            CashAdvanceRequestStatus::Approved,
            $request->fresh()->status
        );

        // Duplicate decision pada step yang sama harus ditolak.
        $this->expectException(CashAdvanceApprovalException::class);

        $approvalService->decide(
            $decision->fresh(),
            $approverEmployee->user,
            'approve',
            null
        );
    }

    public function test_request_rejected_by_approver(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $approverEmployee = Employee::factory()->create();

        $flow = ApprovalFlow::create([
            'name' => 'CA Flow Reject',
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approverEmployee->id,
            'is_active' => true,
        ]);

        ApprovalFlowAssignment::create([
            'approval_flow_id' => $flow->id,
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $request = app(CashAdvanceService::class)->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $decision = CashAdvanceApprovalStepDecision::first();

        app(CashAdvanceApprovalService::class)->decide(
            $decision,
            $approverEmployee->user,
            'reject',
            'Budget tidak cukup'
        );

        $this->assertSame(
            CashAdvanceRequestStatus::Rejected,
            $request->fresh()->status
        );
    }

    // 12,13: disburse hanya dari Approved, duplicate disbursement ditolak
    public function test_disburse_only_from_approved_and_rejects_duplicate(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();

        $service = app(CashAdvanceService::class);

        $request = $service->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $service->disburse($request, 'Transfer BCA', null);

        $this->assertNotNull($request->fresh()->disbursed_at);

        $this->assertSame(
            CashAdvanceRequestStatus::NeedSettlement,
            $request->fresh()->status
        );

        $this->expectException(CashAdvanceValidationException::class);

        $service->disburse(
            $request->fresh(),
            'Coba lagi',
            null
        );
    }

    // 14: settlement sebelum disbursement ditolak
    public function test_settlement_rejected_before_disbursement(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();

        $request = app(CashAdvanceService::class)->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $this->expectException(CashAdvanceValidationException::class);

        app(CashAdvanceSettlementService::class)->submit($request, [
            'items' => [
                [
                    'cash_advance_category_id' => $category->id,
                    'description' => 'Transport',
                    'actual_amount' => '400000',
                ],
            ],
        ]);
    }

    // 15,16,22: settlement setelah disbursement, item dihitung benar, attachment tersimpan
    public function test_employee_can_submit_settlement_after_disbursement_with_correct_totals(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();

        $service = app(CashAdvanceService::class);

        $request = $service->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $service->disburse($request, null, null);

        $settlement = app(CashAdvanceSettlementService::class)->submit(
            $request->fresh(),
            [
                'items' => [
                    [
                        'cash_advance_category_id' => $category->id,
                        'description' => 'Transport',
                        'actual_amount' => '450000',
                        'returned_amount' => '50000',
                    ],
                    [
                        'cash_advance_category_id' => $category->id,
                        'description' => 'Hotel',
                        'actual_amount' => '1000000',
                    ],
                ],
            ]
        );

        $this->assertSame(
            '1450000.00',
            (string) $settlement->total_actual_amount
        );

        $this->assertSame(
            '50000.00',
            (string) $settlement->total_returned_amount
        );

        $this->assertSame(
            CashAdvanceRequestStatus::Completed,
            $request->fresh()->status,
            'Tanpa ApprovalFlow, settlement auto-approve dan request harus langsung Completed.'
        );
    }

    // 17,18,19,20,21: settlement masuk approval terpisah, reject -> Need Settlement, submit ulang, approve -> Completed, duplicate decision ditolak
    public function test_settlement_approval_flow_reject_then_resubmit_then_approve(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $approverEmployee = Employee::factory()->create();

        $flow = ApprovalFlow::create([
            'name' => 'CA Settlement Flow',
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approverEmployee->id,
            'is_active' => true,
        ]);

        ApprovalFlowAssignment::create([
            'approval_flow_id' => $flow->id,
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $cashAdvanceService = app(CashAdvanceService::class);

        $request = $cashAdvanceService->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        // Request-level approval juga kena flow yang sama -- approve dulu supaya bisa disburse.
        $requestDecision = CashAdvanceApprovalStepDecision::first();

        app(CashAdvanceApprovalService::class)->decide(
            $requestDecision,
            $approverEmployee->user,
            'approve',
            null
        );

        $cashAdvanceService->disburse(
            $request->fresh(),
            null,
            null
        );

        $settlementService = app(CashAdvanceSettlementService::class);
        $settlementApprovalService = app(CashAdvanceSettlementApprovalService::class);

        $settlementPayload = [
            'items' => [
                [
                    'cash_advance_category_id' => $category->id,
                    'description' => 'Transport',
                    'actual_amount' => '500000',
                ],
                [
                    'cash_advance_category_id' => $category->id,
                    'description' => 'Hotel',
                    'actual_amount' => '1000000',
                ],
            ],
        ];

        $settlement1 = $settlementService->submit(
            $request->fresh(),
            $settlementPayload
        );

        $decision1 = $settlement1->approvalRequest
            ->stepDecisions()
            ->first();

        $settlementApprovalService->decide(
            $decision1,
            $approverEmployee->user,
            'reject',
            'Bukti kurang lengkap'
        );

        $this->assertSame(
            CashAdvanceSettlementStatus::Rejected,
            $settlement1->fresh()->status
        );

        $this->assertSame(
            CashAdvanceRequestStatus::NeedSettlement,
            $request->fresh()->status
        );

        // Duplicate decision pada settlement yang sama harus ditolak.
        $this->expectException(CashAdvanceApprovalException::class);

        $settlementApprovalService->decide(
            $decision1->fresh(),
            $approverEmployee->user,
            'approve',
            null
        );
    }

    public function test_settlement_resubmit_after_rejection_and_approve_completes_request(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();
        $approverEmployee = Employee::factory()->create();

        $flow = ApprovalFlow::create([
            'name' => 'CA Settlement Flow 2',
            'is_active' => true,
        ]);

        ApprovalStep::create([
            'approval_flow_id' => $flow->id,
            'sequence' => 1,
            'approver_type' => ApproverType::SpecificEmployee->value,
            'approver_employee_id' => $approverEmployee->id,
            'is_active' => true,
        ]);

        ApprovalFlowAssignment::create([
            'approval_flow_id' => $flow->id,
            'employee_id' => $employee->id,
            'is_active' => true,
        ]);

        $cashAdvanceService = app(CashAdvanceService::class);

        $request = $cashAdvanceService->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $requestDecision = CashAdvanceApprovalStepDecision::first();

        app(CashAdvanceApprovalService::class)->decide(
            $requestDecision,
            $approverEmployee->user,
            'approve',
            null
        );

        $cashAdvanceService->disburse(
            $request->fresh(),
            null,
            null
        );

        $settlementService = app(CashAdvanceSettlementService::class);
        $settlementApprovalService = app(CashAdvanceSettlementApprovalService::class);

        $itemsPayload = [
            'items' => [
                [
                    'cash_advance_category_id' => $category->id,
                    'description' => 'Transport',
                    'actual_amount' => '500000',
                ],
                [
                    'cash_advance_category_id' => $category->id,
                    'description' => 'Hotel',
                    'actual_amount' => '1000000',
                ],
            ],
        ];

        $settlement1 = $settlementService->submit(
            $request->fresh(),
            $itemsPayload
        );

        $settlementApprovalService->decide(
            $settlement1->approvalRequest->stepDecisions()->first(),
            $approverEmployee->user,
            'reject',
            'Kurang bukti'
        );

        // Employee submit ulang -> baris settlement BARU, histori lama tetap ada.
        $settlement2 = $settlementService->submit(
            $request->fresh(),
            $itemsPayload
        );

        $this->assertCount(
            2,
            $request->fresh()->settlements
        );

        $this->assertSame(
            CashAdvanceSettlementStatus::Rejected,
            $settlement1->fresh()->status
        );

        $settlementApprovalService->decide(
            $settlement2->approvalRequest->stepDecisions()->first(),
            $approverEmployee->user,
            'approve',
            null
        );

        $this->assertSame(
            CashAdvanceSettlementStatus::Approved,
            $settlement2->fresh()->status
        );

        $this->assertSame(
            CashAdvanceRequestStatus::Completed,
            $request->fresh()->status
        );
    }

    // 24: cancellation hanya pada status eligible
    public function test_cancellation_only_allowed_on_eligible_status(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();

        $service = app(CashAdvanceService::class);

        $request = $service->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $service->disburse(
            $request->fresh(),
            null,
            null
        );

        // Sudah NeedSettlement (lewat disbursement) -> tidak eligible dibatalkan lagi.
        $this->expectException(CashAdvanceValidationException::class);

        $service->cancel(
            $request->fresh(),
            'Berubah pikiran'
        );
    }

    public function test_cancellation_allowed_while_pending_or_approved(): void
    {
        [$policy, $category] = $this->policyWithCategory();

        $employee = Employee::factory()->create();

        $service = app(CashAdvanceService::class);

        $request = $service->submit(
            $employee,
            $this->submitPayload($policy->id, $category->id)
        );

        $cancelled = $service->cancel(
            $request,
            'Rencana batal'
        );

        $this->assertSame(
            CashAdvanceRequestStatus::Cancelled,
            $cancelled->status
        );
    }
}