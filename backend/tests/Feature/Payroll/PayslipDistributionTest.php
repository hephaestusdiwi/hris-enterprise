<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Payroll\Contracts\PayrollCalculationEngineInterface;
use App\Modules\Payroll\DataTransferObjects\EmployeePayslipDraft;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Notifications\PayslipPublishedNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PayslipDistributionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->app->bind(PayrollCalculationEngineInterface::class, function () {
            return new class implements PayrollCalculationEngineInterface
            {
                public function calculateDraftsForRun(PayrollRun $run): array
                {
                    $drafts = [];
                    foreach ($run->participants as $employee) {
                        $drafts[$employee->id] = new EmployeePayslipDraft(
                            employeeId: $employee->id, grossEarning: '6000000.00', structuralDeduction: '0.00',
                            manualDeductionTotal: '0.00', bpjsEmployeeTotal: '100000.00', bpjsEmployerTotal: '200000.00',
                            taxAmount: '50000.00', loanDeductionTotal: '0.00', netPay: '5850000.00', lines: [],
                        );
                    }

                    return $drafts;
                }
            };
        });
    }

    private function makeLockedRun(Employee $employee): PayrollRun
    {
        $response = $this->actingAs($this->admin)->postJson('/api/payroll-runs', [
            'company_id' => $this->company->id, 'period_year' => 2026, 'period_month' => 6,
            'employee_ids' => [$employee->id],
        ]);
        $run = PayrollRun::findOrFail($response->json('data.id'));
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/proceed-payslip")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/request-approval")->assertOk();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/lock")->assertOk();

        return $run->fresh();
    }

    // ---------- Notifikasi saat publish (bulk, via PayrollRunService) ----------

    public function test_publishing_payroll_run_notifies_each_employee(): void
    {
        Notification::fake();

        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUser->id]);
        $run = $this->makeLockedRun($employee);

        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/publish")->assertOk();

        Notification::assertSentTo($employeeUser, PayslipPublishedNotification::class);
    }

    public function test_publishing_does_not_fail_when_employee_has_no_user_account(): void
    {
        Notification::fake();

        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUser->id]);
        $run = $this->makeLockedRun($employee);

        // Akun user-nya di-soft-delete SETELAH payroll di-lock (skenario:
        // employee resign/dinonaktifkan sebelum payslip sempat dipublish) —
        // relasi employee->user (belongsTo, otomatis exclude soft-deleted)
        // jadi null. Publish TIDAK BOLEH throw/500 cuma karena ini.
        $employeeUser->delete();

        $this->actingAs($this->admin)
            ->postJson("/api/payroll-runs/{$run->id}/publish")
            ->assertOk();
    }

    // ---------- Notifikasi saat publish individual (via PayslipController) ----------

    public function test_publishing_individual_payslip_notifies_employee(): void
    {
        Notification::fake();

        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUser->id]);
        $run = $this->makeLockedRun($employee);
        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();

        // unpublish dulu (publish run tadi udah mempublish semuanya), lalu publish ulang via endpoint individual.
        $this->actingAs($this->admin)->postJson("/api/payslips/{$payslip->id}/unpublish")->assertOk();
        Notification::fake(); // reset supaya cuma notifikasi dari publish individual yang ke-assert

        $this->actingAs($this->admin)->postJson("/api/payslips/{$payslip->id}/publish")->assertOk();

        Notification::assertSentTo($employeeUser, PayslipPublishedNotification::class);
    }

    // ---------- Download PDF — HR ----------

    public function test_hr_can_download_payslip_pdf(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->makeLockedRun($employee);
        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();

        $response = $this->actingAs($this->admin)->get("/api/payslips/{$payslip->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_hr_download_requires_view_payroll_runs_permission(): void
    {
        $employee = Employee::factory()->create(['company_id' => $this->company->id]);
        $run = $this->makeLockedRun($employee);
        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $userWithoutPermission = User::factory()->create();

        $this->actingAs($userWithoutPermission)
            ->get("/api/payslips/{$payslip->id}/download")
            ->assertForbidden();
    }

    // ---------- Download PDF — Employee self-service ----------

    public function test_employee_can_download_own_published_payslip(): void
    {
        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUser->id]);
        $run = $this->makeLockedRun($employee);
        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/publish")->assertOk();

        $response = $this->actingAs($employeeUser)->get("/api/my-payslips/{$payslip->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_cannot_download_unpublished_payslip(): void
    {
        $employeeUser = User::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUser->id]);
        $run = $this->makeLockedRun($employee);
        $payslip = Payslip::where('employee_id', $employee->id)->where('payroll_run_id', $run->id)->first();
        // Sengaja TIDAK publish.

        $this->actingAs($employeeUser)
            ->get("/api/my-payslips/{$payslip->id}/download")
            ->assertForbidden();
    }

    public function test_employee_cannot_download_other_employees_payslip(): void
    {
        $employeeUserA = User::factory()->create();
        $employeeA = Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUserA->id]);
        $employeeUserB = User::factory()->create();
        Employee::factory()->create(['company_id' => $this->company->id, 'user_id' => $employeeUserB->id]);

        $run = $this->makeLockedRun($employeeA);
        $payslipA = Payslip::where('employee_id', $employeeA->id)->where('payroll_run_id', $run->id)->first();
        $this->actingAs($this->admin)->postJson("/api/payroll-runs/{$run->id}/publish")->assertOk();

        // employeeUserB coba download payslip milik employeeA.
        $this->actingAs($employeeUserB)
            ->get("/api/my-payslips/{$payslipA->id}/download")
            ->assertForbidden();
    }
}
