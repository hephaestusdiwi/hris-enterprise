<?php

namespace Tests\Feature\Expense;

use App\Modules\Company\Models\Company;
use App\Modules\Employee\Models\Employee;
use App\Modules\Expense\Enums\ExpenseClaimStatus;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Expense\Models\ExpenseClaim;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Models\ExpensePolicyAssignment;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseClaimHttpEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function actingEmployee(): Employee
    {
        $this->seed(RolePermissionSeeder::class);

        $employee = Employee::factory()->create();
        $employee->user->assignRole('employee');

        return $employee;
    }

    private function category(int $companyId): ExpenseCategory
    {
        return ExpenseCategory::create([
            'company_id' => $companyId,
            'name' => 'Transportasi',
            'code' => 'TRANSPORT-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function policyWithCategory(int $companyId): array
    {
        $policy = ExpensePolicy::create([
            'company_id' => $companyId,
            'name' => 'Kebijakan Expense',
            'effective_date' => now()->subYear()->toDateString(),
            'is_active' => true,
        ]);

        $category = $this->category($companyId);
        $policy->categories()->sync([$category->id => ['limit_amount' => null]]);

        return [$policy, $category];
    }

    private function assignPolicy(Employee $employee, ExpensePolicy $policy): ExpensePolicyAssignment
    {
        return ExpensePolicyAssignment::create([
            'employee_id' => $employee->id,
            'expense_policy_id' => $policy->id,
            'effective_date' => now()->subMonth()->toDateString(),
            'is_active' => true,
        ]);
    }

    private function createClaim(Employee $employee): ExpenseClaim
    {
        [$policy, $category] = $this->policyWithCategory($employee->company_id);
        $this->assignPolicy($employee, $policy);

        $response = $this->actingAs($employee->user)->postJson('/api/my-expense-claims', [
            'expense_category_id' => $category->id,
            'expense_date' => now()->toDateString(),
            'amount' => '100000',
            'description' => 'Test claim',
        ]);

        return ExpenseClaim::findOrFail($response->json('data.id'));
    }

    // 15: attachment bisa diupload (multi-file, key `attachments`).
    public function test_attachment_can_be_uploaded(): void
    {
        Storage::fake('public');
        $employee = $this->actingEmployee();
        $claim = $this->createClaim($employee);

        $response = $this->actingAs($employee->user)
            ->post("/api/expense-claims/{$claim->id}/attachments", [
                'attachments' => [
                    UploadedFile::fake()->create('bukti1.pdf', 200, 'application/pdf'),
                    UploadedFile::fake()->create('bukti2.jpg', 300, 'image/jpeg'),
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJsonCount(2, 'data');
        $this->assertSame(2, $claim->fresh()->attachments()->count());

        foreach ($claim->fresh()->attachments as $attachment) {
            Storage::disk('public')->assertExists($attachment->file_path);
        }
    }

    // 16: attachment format tidak valid -> ditolak.
    public function test_invalid_attachment_is_rejected(): void
    {
        Storage::fake('public');
        $employee = $this->actingEmployee();
        $claim = $this->createClaim($employee);

        $response = $this->actingAs($employee->user)
            ->post("/api/expense-claims/{$claim->id}/attachments", [
                'attachments' => [
                    UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'),
                ],
            ]);

        $response->assertStatus(422);
    }

    // 17: employee tidak bisa lihat claim employee lain.
    public function test_employee_cannot_access_another_employees_claim(): void
    {
        $owner = $this->actingEmployee();
        $claim = $this->createClaim($owner);

        $stranger = Employee::factory()->create();
        $stranger->user->assignRole('employee');

        $response = $this->actingAs($stranger->user)
            ->getJson("/api/my-expense-claims/{$claim->id}");

        $response->assertStatus(403);
    }

    // 18: employee tidak bisa cancel claim employee lain.
    public function test_employee_cannot_cancel_another_employees_claim(): void
    {
        $owner = $this->actingEmployee();
        $claim = $this->createClaim($owner);

        $stranger = Employee::factory()->create();
        $stranger->user->assignRole('employee');

        $response = $this->actingAs($stranger->user)
            ->postJson("/api/expense-claims/{$claim->id}/cancel", [
                'reason' => 'Coba batalkan punya orang lain',
            ]);

        $response->assertStatus(403);
    }

    // 19: claim Pending bisa dibatalkan oleh pemiliknya.
    public function test_pending_claim_can_be_cancelled_by_owner(): void
    {
        $employee = $this->actingEmployee();
        $claim = $this->createClaim($employee);

        $this->assertSame(ExpenseClaimStatus::Approved, $claim->fresh()->status);

        $response = $this->actingAs($employee->user)
            ->postJson("/api/expense-claims/{$claim->id}/cancel", [
                'reason' => 'Rencana batal',
            ]);

        $response->assertStatus(200);
        $this->assertSame(ExpenseClaimStatus::Cancelled, $claim->fresh()->status);
        $this->assertSame('Rencana batal', $claim->fresh()->cancel_reason);
    }

    // 20: claim yang sudah Cancelled tidak bisa dibatalkan lagi.
    public function test_already_cancelled_claim_cannot_be_cancelled_again(): void
    {
        $employee = $this->actingEmployee();
        $claim = $this->createClaim($employee);

        $this->actingAs($employee->user)->postJson("/api/expense-claims/{$claim->id}/cancel", [
            'reason' => 'Batal pertama',
        ]);

        $response = $this->actingAs($employee->user)->postJson("/api/expense-claims/{$claim->id}/cancel", [
            'reason' => 'Coba batalkan lagi',
        ]);

        $response->assertStatus(422);
    }

    // 23: company isolation -- category dari company lain ditolak (regresi
    // dari validasi resolve policy/category yang company-scoped).
    public function test_company_isolation_between_employee_and_category(): void
    {
        $employee = $this->actingEmployee();
        $otherCompany = Company::factory()->create();

        [$policy] = $this->policyWithCategory($employee->company_id);
        $this->assignPolicy($employee, $policy);

        // Category company lain -- tidak mungkin masuk $policy->categories
        // karena STEP 2 sudah company-scope validasi attach category.
        $foreignCategory = $this->category($otherCompany->id);

        $response = $this->actingAs($employee->user)->postJson('/api/my-expense-claims', [
            'expense_category_id' => $foreignCategory->id,
            'expense_date' => now()->toDateString(),
            'amount' => '100000',
        ]);

        $response->assertStatus(422);
    }

    // 24: user tanpa permission 'view expense claims' ditolak di endpoint
    // management (bukan self-service).
    public function test_unauthorized_user_gets_403_on_management_listing(): void
    {
        $employee = $this->actingEmployee();

        $response = $this->actingAs($employee->user)->getJson('/api/expense-claims');

        $response->assertStatus(403);
    }

    public function test_hr_can_view_and_cancel_any_claim(): void
    {
        $employee = $this->actingEmployee();
        $claim = $this->createClaim($employee);

        $hr = Employee::factory()->create(['company_id' => $employee->company_id]);
        $hr->user->assignRole('hr');

        $viewResponse = $this->actingAs($hr->user)->getJson('/api/expense-claims');
        $viewResponse->assertStatus(200);

        $cancelResponse = $this->actingAs($hr->user)->postJson("/api/expense-claims/{$claim->id}/cancel", [
            'reason' => 'Dibatalkan HR',
        ]);
        $cancelResponse->assertStatus(200);
    }
}