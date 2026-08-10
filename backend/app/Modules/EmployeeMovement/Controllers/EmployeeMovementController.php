<?php

namespace App\Modules\EmployeeMovement\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeMovement\Contracts\EmployeeMovementScopeInterface;
use App\Modules\EmployeeMovement\Enums\EmployeeMovementType;
use App\Modules\EmployeeMovement\Models\EmployeeMovement;
use App\Modules\EmployeeMovement\Requests\StoreEmployeeMovementRequest;
use App\Modules\EmployeeMovement\Services\EmployeeMovementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeMovementController extends Controller
{
    public function __construct(
        private EmployeeMovementService $service,
        private EmployeeMovementScopeInterface $scope,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $movements = $this->scope
            ->apply(EmployeeMovement::query()->with(['employee', 'requestedBy'])->latest('effective_date'), $request->user())
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $movements]);
    }

    public function store(Employee $employee, StoreEmployeeMovementRequest $request): JsonResponse
    {
        $this->authorize('view', $employee);

        $movement = $this->service->create(
            $employee,
            EmployeeMovementType::from($request->validated('movement_type')),
            Carbon::parse($request->validated('effective_date')),
            $request->afterValues(),
            $request->user()->id,
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Employee movement berhasil diajukan.',
            'data' => $movement,
        ], 201);
    }

    public function show(EmployeeMovement $employeeMovement): JsonResponse
    {
        $this->authorize('view', $employeeMovement->employee);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employeeMovement->load(['employee', 'requestedBy', 'approvalRequest.stepDecisions']),
        ]);
    }

    public function cancel(EmployeeMovement $employeeMovement): JsonResponse
    {
        $this->authorize('update', $employeeMovement->employee);

        $movement = $this->service->cancel($employeeMovement);

        return response()->json(['success' => true, 'message' => 'Movement dibatalkan.', 'data' => $movement]);
    }
}
