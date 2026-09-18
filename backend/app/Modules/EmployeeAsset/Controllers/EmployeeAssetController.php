<?php

namespace App\Modules\EmployeeAsset\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Models\Employee;
use App\Modules\EmployeeAsset\Models\EmployeeAsset;
use App\Modules\EmployeeAsset\Requests\StoreEmployeeAssetRequest;
use App\Modules\EmployeeAsset\Requests\UpdateEmployeeAssetRequest;
use Illuminate\Http\Request;

/**
 * Employee Assets (Assets tab ala Mekari Talenta).
 *
 * BEDA dari Files: assignment aset perusahaan (laptop, ID card, dst) itu
 * keputusan HR sepenuhnya, bukan sesuatu yang bisa diinisiasi employee
 * sendiri -- jadi self-service DI SINI cuma read-only (GET /my-assets),
 * TIDAK ada storeMine/destroyMine seperti EmployeeDocument.
 */
class EmployeeAssetController extends Controller
{
    // ---------- Self-service (read-only) ----------

    public function indexMine(Request $request)
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 422, 'User ini tidak terhubung dengan data employee.');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->assets()->latest('assigned_date')->get(),
        ]);
    }

    // ---------- Admin ----------

    public function indexForEmployee(Employee $employee)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->assets()->latest('assigned_date')->get(),
        ]);
    }

    public function storeForEmployee(StoreEmployeeAssetRequest $request, Employee $employee)
    {
        $asset = $employee->assets()->create([
            ...$request->validated(),
            'assigned_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Asset berhasil ditambahkan',
            'data' => $asset,
        ], 201);
    }

    public function updateForEmployee(UpdateEmployeeAssetRequest $request, Employee $employee, EmployeeAsset $asset)
    {
        abort_unless($asset->employee_id === $employee->id, 404);

        $asset->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Asset berhasil diperbarui',
            'data' => $asset,
        ]);
    }

    public function destroyForEmployee(Employee $employee, EmployeeAsset $asset)
    {
        abort_unless($asset->employee_id === $employee->id, 404);

        $asset->delete();

        return response()->json(['success' => true, 'message' => 'Asset berhasil dihapus', 'data' => null]);
    }
}