<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Contracts\ContractProbationServiceInterface;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\Request;

class ContractProbationController extends Controller
{
    public function __construct(private ContractProbationServiceInterface $service)
    {
    }

    /**
     * Dipakai dua tempat: dashboard widget (summary) dan dedicated list page
     * (full, dengan filter). Tidak dipaginate — skalanya kecil (cuma
     * employee yang masuk threshold), sesuai instruksi jangan overengineer.
     */
    public function index(Request $request)
    {
        $items = $this->service->upcoming($request->user());

        $type = $request->query('type'); // 'contract' | 'probation' | null
        if ($type) {
            $items = $items->where('type', $type);
        }

        $search = $request->query('search');
        if ($search) {
            $needle = mb_strtolower($search);
            $items = $items->filter(function (array $item) use ($needle) {
                /** @var Employee $employee */
                $employee = $item['employee'];
                $name = mb_strtolower(trim("{$employee->first_name} {$employee->last_name}"));

                return str_contains($name, $needle)
                    || str_contains(mb_strtolower($employee->employee_number), $needle);
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $items->values()->map(fn (array $item) => $this->formatItem($item)),
        ]);
    }

    /**
     * Ringkasan buat dashboard widget — count doang, tanpa perlu request
     * terpisah untuk detail per item.
     */
    public function summary(Request $request)
    {
        $items = $this->service->upcoming($request->user());

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'contract_ending_soon' => $items->where('type', 'contract')->count(),
                'probation_ending_soon' => $items->where('type', 'probation')->count(),
                'preview' => $items->take(5)->values()->map(fn (array $item) => $this->formatItem($item)),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatItem(array $item): array
    {
        /** @var Employee $employee */
        $employee = $item['employee'];

        return [
            'type' => $item['type'],
            'end_date' => $item['end_date']->toDateString(),
            'remaining_days' => $item['remaining_days'],
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'photo_url' => $employee->photo_url,
                'position' => $employee->position?->name,
            ],
            'employment_type' => $employee->employmentType?->name,
            'employment_status' => $employee->employmentStatus?->name,
            'manager' => $employee->manager ? [
                'id' => $employee->manager->id,
                'name' => trim("{$employee->manager->first_name} {$employee->manager->last_name}"),
            ] : null,
        ];
    }
}
