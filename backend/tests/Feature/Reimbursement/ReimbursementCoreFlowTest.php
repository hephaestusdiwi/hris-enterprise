<?php

namespace Tests\Feature\Reimbursement;

use App\Modules\Employee\Models\Employee;
use App\Modules\Reimbursement\Enums\ReimbursementRequestStatus;
use App\Modules\Reimbursement\Exceptions\ReimbursementValidationException;
use App\Modules\Reimbursement\Models\ReimbursementBenefit;
use App\Modules\Reimbursement\Models\ReimbursementPolicy;
use App\Modules\Reimbursement\Services\ReimbursementBalanceService;
use App\Modules\Reimbursement\Services\ReimbursementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReimbursementCoreFlowTest extends TestCase
{
    use RefreshDatabase;

    private function policyWithBenefit(
        ?string $defaultLimit = '1000000.00'
    ): array {
        $policy = ReimbursementPolicy::create([
            'name' => 'Medical',
            'effective_date' => '2027-01-01',
            'default_limit_amount' => $defaultLimit,
            'is_active' => true,
        ]);

        $benefit = ReimbursementBenefit::create([
            'reimbursement_policy_id' => $policy->id,
            'name' => 'Konsultasi Dokter',
            'is_active' => true,
        ]);

        return [
            $policy,
            $benefit,
        ];
    }

    public function test_assign_creates_balance_with_initial_ledger_entry(): void
    {
        [$policy] = $this->policyWithBenefit();

        $employee = Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $this->assertSame(
            '1000000.00',
            $balance->remainingBalance()
        );

        $this->assertSame(
            1,
            $balance->transactions()->count()
        );
    }

    public function test_submit_with_no_approval_flow_auto_approves_and_deducts_balance(): void
    {
        [$policy, $benefit] =
            $this->policyWithBenefit();

        $employee =
            Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $request = app(
            ReimbursementService::class
        )->submit(
            $employee,
            [
                'reimbursement_balance_id' =>
                    $balance->id,

                'transaction_date' =>
                    '2027-01-10',

                'items' => [
                    [
                        'reimbursement_benefit_id' =>
                            $benefit->id,

                        'amount' =>
                            '300000',
                    ],
                ],
            ]
        );

        $this->assertSame(
            ReimbursementRequestStatus::Approved,
            $request->fresh()->status
        );

        $this->assertSame(
            '300000.00',
            (string) $request->total_amount
        );

        $this->assertSame(
            '700000.00',
            $balance->fresh()->remainingBalance()
        );
    }

    public function test_submit_rejected_when_amount_exceeds_remaining_balance(): void
    {
        [$policy, $benefit] =
            $this->policyWithBenefit(
                '200000.00'
            );

        $employee =
            Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $this->expectException(
            ReimbursementValidationException::class
        );

        app(
            ReimbursementService::class
        )->submit(
            $employee,
            [
                'reimbursement_balance_id' =>
                    $balance->id,

                'transaction_date' =>
                    '2027-01-10',

                'items' => [
                    [
                        'reimbursement_benefit_id' =>
                            $benefit->id,

                        'amount' =>
                            '500000',
                    ],
                ],
            ]
        );
    }

    public function test_submit_rejected_when_benefit_does_not_belong_to_policy(): void
    {
        [$policy] =
            $this->policyWithBenefit();

        [, $foreignBenefit] =
            $this->policyWithBenefit();

        $employee =
            Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $this->expectException(
            ReimbursementValidationException::class
        );

        app(
            ReimbursementService::class
        )->submit(
            $employee,
            [
                'reimbursement_balance_id' =>
                    $balance->id,

                'transaction_date' =>
                    '2027-01-10',

                'items' => [
                    [
                        'reimbursement_benefit_id' =>
                            $foreignBenefit->id,

                        'amount' =>
                            '100000',
                    ],
                ],
            ]
        );
    }

    public function test_cancel_approved_request_reverses_ledger_and_restores_balance(): void
    {
        [$policy, $benefit] =
            $this->policyWithBenefit();

        $employee =
            Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $service =
            app(ReimbursementService::class);

        $request =
            $service->submit(
                $employee,
                [
                    'reimbursement_balance_id' =>
                        $balance->id,

                    'transaction_date' =>
                        '2027-01-10',

                    'items' => [
                        [
                            'reimbursement_benefit_id' =>
                                $benefit->id,

                            'amount' =>
                                '300000',
                        ],
                    ],
                ]
            );

        $this->assertSame(
            '700000.00',
            $balance->fresh()->remainingBalance()
        );

        $service->cancel(
            $request,
            'Salah input'
        );

        $this->assertSame(
            ReimbursementRequestStatus::Cancelled,
            $request->fresh()->status
        );

        $this->assertSame(
            3,
            $balance->transactions()->count()
        );

        $this->assertSame(
            '1000000.00',
            $balance->fresh()->remainingBalance()
        );
    }

    public function test_disburse_cannot_be_done_twice(): void
    {
        [$policy, $benefit] =
            $this->policyWithBenefit();

        $employee =
            Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $service =
            app(ReimbursementService::class);

        $request =
            $service->submit(
                $employee,
                [
                    'reimbursement_balance_id' =>
                        $balance->id,

                    'transaction_date' =>
                        '2027-01-10',

                    'items' => [
                        [
                            'reimbursement_benefit_id' =>
                                $benefit->id,

                            'amount' =>
                                '300000',
                        ],
                    ],
                ]
            );

        $service->disburse(
            $request,
            'Dibayar via transfer',
            null
        );

        $this->assertNotNull(
            $request->fresh()->disbursed_at
        );

        $this->expectException(
            ReimbursementValidationException::class
        );

        $service->disburse(
            $request->fresh(),
            'Coba lagi',
            null
        );
    }

    public function test_balance_cannot_go_negative_across_two_requests(): void
    {
        [$policy, $benefit] =
            $this->policyWithBenefit(
                '500000.00'
            );

        $employee =
            Employee::factory()->create();

        $balance = app(
            ReimbursementBalanceService::class
        )->assign(
            $employee,
            $policy,
            [
                'effective_date' => '2027-01-01',
            ],
            null
        );

        $service =
            app(ReimbursementService::class);

        $service->submit(
            $employee,
            [
                'reimbursement_balance_id' =>
                    $balance->id,

                'transaction_date' =>
                    '2027-01-10',

                'items' => [
                    [
                        'reimbursement_benefit_id' =>
                            $benefit->id,

                        'amount' =>
                            '400000',
                    ],
                ],
            ]
        );

        $this->assertSame(
            '100000.00',
            $balance->fresh()->remainingBalance()
        );

        $this->expectException(
            ReimbursementValidationException::class
        );

        $service->submit(
            $employee,
            [
                'reimbursement_balance_id' =>
                    $balance->id,

                'transaction_date' =>
                    '2027-01-11',

                'items' => [
                    [
                        'reimbursement_benefit_id' =>
                            $benefit->id,

                        'amount' =>
                            '200000',
                    ],
                ],
            ]
        );
    }
}