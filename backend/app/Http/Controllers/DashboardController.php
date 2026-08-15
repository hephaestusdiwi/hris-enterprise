<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Employee\Contracts\ContractProbationServiceInterface;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __construct(private ContractProbationServiceInterface $contractProbationService)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'stats' => $this->statsForUser($user),
                'contract_probation' => $this->contractProbationSummary($user),
            ],
        ]);
    }

    /**
     * Cuma dihitung kalau user punya permission 'view employees' — user
     * yang nggak punya akses Employee sama sekali (mis. role employee polos
     * tanpa hierarchy) nggak perlu query ini jalan sia-sia.
     */
    private function contractProbationSummary(User $user): ?array
    {
        if (! $user->can('view employees')) {
            return null;
        }

        $items = $this->contractProbationService->upcoming($user);

        return [
            'contract_ending_soon' => $items->where('type', 'contract')->count(),
            'probation_ending_soon' => $items->where('type', 'probation')->count(),
        ];
    }

    /**
     * Stats masih dummy — modul Employee/Attendance/Payroll dll baru mulai
     * PHASE 2+. Gampang diganti data asli nanti begitu module-nya ada.
     */
    private function statsForUser(User $user): array
    {
        if ($user->hasRole('admin')) {
            return [
                ['label' => 'Total Users', 'value' => User::count()],
                ['label' => 'Total Roles', 'value' => Role::count()],
            ];
        }

        if ($user->hasRole('hr')) {
            return [
                ['label' => 'Total Users', 'value' => User::count()],
            ];
        }

        return [
            ['label' => 'Status', 'value' => 'Active'],
        ];
    }
}