<?php

namespace App\Modules\CompanyObligation\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CompanyObligation\Contracts\CompanyObligationScopeInterface;
use App\Modules\CompanyObligation\Enums\CompanyObligationStatus;
use App\Modules\CompanyObligation\Models\CompanyObligation;
use App\Modules\CompanyObligation\Requests\StoreCompanyObligationRequest;
use App\Modules\CompanyObligation\Requests\UpdateCompanyObligationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyObligationController extends Controller
{
    public function __construct(private CompanyObligationScopeInterface $scope)
    {
    }

    // ---------- Management (permission 'view/create/edit/delete company obligations') ----------

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CompanyObligation::class);

        $obligations = CompanyObligation::query()
            ->with(['company', 'pic', 'recipients.user'])
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy('due_date')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $obligations,
            'meta' => ['types' => CompanyObligation::TYPES],
        ]);
    }

    public function store(StoreCompanyObligationRequest $request): JsonResponse
    {
        $this->authorize('create', CompanyObligation::class);

        $obligation = CompanyObligation::create([
            ...$request->validated(),
            'status' => CompanyObligationStatus::Active,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company Obligation berhasil dibuat',
            'data' => $obligation->load(['company', 'pic']),
        ], 201);
    }

    public function show(CompanyObligation $companyObligation): JsonResponse
    {
        $companyObligation->load('recipients');
        $this->authorize('view', $companyObligation);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $companyObligation->load(['company', 'pic.user', 'createdBy', 'recipients.user']),
        ]);
    }

    public function update(UpdateCompanyObligationRequest $request, CompanyObligation $companyObligation): JsonResponse
    {
        $this->authorize('update', $companyObligation);

        $companyObligation->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company Obligation berhasil diperbarui',
            'data' => $companyObligation->fresh()->load(['company', 'pic']),
        ]);
    }

    public function destroy(CompanyObligation $companyObligation): JsonResponse
    {
        $this->authorize('delete', $companyObligation);

        $companyObligation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company Obligation berhasil dihapus',
            'data' => null,
        ]);
    }

    // ---------- Self-service (read-only) ----------

    public function indexMine(Request $request): JsonResponse
    {
        $obligations = $this->scope
            ->applyMine(CompanyObligation::query(), $request->user())
            ->with(['company', 'pic'])
            ->orderBy('due_date')
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $obligations]);
    }
}