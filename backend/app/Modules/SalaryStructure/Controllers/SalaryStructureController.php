<?php

namespace App\Modules\SalaryStructure\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SalaryStructure\Models\SalaryStructure;
use App\Modules\SalaryStructure\Requests\StoreSalaryStructureRequest;
use Illuminate\Support\Facades\DB;

class SalaryStructureController extends Controller
{
    public function index()
    {
        $structures = SalaryStructure::with('company')
            ->withCount('details')
            ->orderByDesc('effective_date')
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $structures]);
    }

    public function store(StoreSalaryStructureRequest $request)
    {
        $validated = $request->validated();

        $structure = DB::transaction(function () use ($validated) {
            $structure = SalaryStructure::create([
                'company_id' => $validated['company_id'],
                'code' => $validated['code'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'effective_date' => $validated['effective_date'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['details'] as $index => $detail) {
                $structure->details()->create([
                    'salary_component_id' => $detail['salary_component_id'],
                    'override_amount' => $detail['override_amount'] ?? null,
                    'override_percentage_value' => $detail['override_percentage_value'] ?? null,
                    'override_percentage_base' => $detail['override_percentage_base'] ?? null,
                    'display_order' => $detail['display_order'] ?? $index,
                ]);
            }

            return $structure;
        });

        return response()->json([
            'success' => true,
            'message' => 'Salary Structure berhasil dibuat',
            'data' => $structure->load(['company', 'details.salaryComponent']),
        ], 201);
    }

    public function show(SalaryStructure $salaryStructure)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $salaryStructure->load(['company', 'details.salaryComponent']),
        ]);
    }

    /**
     * Buat versi baru dari struktur yang sudah ada (clone detail sebagai starting point,
     * effective_date baru). Ini BUKAN update — hasilnya row baru, versi lama tidak tersentuh.
     */
    public function createNewVersion(StoreSalaryStructureRequest $request, SalaryStructure $salaryStructure)
    {
        return $this->store($request);
    }

    public function versions(string $code, SalaryStructure $anyVersion = null)
    {
        // Endpoint bantu: lihat seluruh histori versi untuk 1 code tertentu
    }

    public function destroy(SalaryStructure $salaryStructure)
    {
        // TODO (STEP 54 - Payroll Generator): tolak kalau struktur ini pernah dipakai
        // menghasilkan payroll manapun. Belum ada tabel payroll di step ini, jadi
        // pengecekan itu belum bisa ditegakkan sekarang.
        $salaryStructure->delete();

        return response()->json(['success' => true, 'message' => 'Salary Structure berhasil dihapus', 'data' => null]);
    }
}