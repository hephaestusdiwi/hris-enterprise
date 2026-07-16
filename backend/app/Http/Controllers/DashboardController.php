<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
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
            ],
        ]);
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