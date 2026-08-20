<?php

namespace App\Modules\ApprovalFlow\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ApprovalFlow\Models\ApprovalFlow;
use App\Modules\ApprovalFlow\Requests\StoreApprovalFlowRequest;
use App\Modules\ApprovalFlow\Requests\UpdateApprovalFlowRequest;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ApprovalFlowController extends Controller
{
    public function index()
    {
        $approvalFlows = ApprovalFlow::with([
                'company',
                'branch',
                'department',
                'jobLevel',
            ])
            ->withCount('steps')
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $approvalFlows,
        ]);
    }

    public function roles()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => Role::select('id', 'name')->get(),
        ]);
    }

    public function store(StoreApprovalFlowRequest $request)
    {
        $data = $request->validated();

        $approvalFlow = DB::transaction(function () use ($data) {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                ApprovalFlow::query()
                    ->where('company_id', $data['company_id'])
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                    ]);

                // Pastikan tidak ada default lama yang tersisa.
                // Ini penting sebelum INSERT karena PostgreSQL punya
                // partial unique constraint untuk default per company.
                $data['is_default'] = true;
            }

            return ApprovalFlow::create($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Approval Flow berhasil dibuat',
            'data' => $approvalFlow->load([
                'company',
                'branch',
                'department',
            ]),
        ], 201);
    }

    public function show(ApprovalFlow $approvalFlow)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $approvalFlow->load([
                'company',
                'branch',
                'department',
                'jobLevel',
                'steps',
                'assignments.employee',
            ]),
        ]);
    }

    public function update(
        UpdateApprovalFlowRequest $request,
        ApprovalFlow $approvalFlow
    ) {
        $data = $request->validated();

        DB::transaction(function () use ($data, $approvalFlow) {
            /*
             * Kalau flow ini dijadikan default,
             * cabut default dari flow lain dalam company yang sama.
             */
            if (! empty($data['is_default'])) {
                ApprovalFlow::where('company_id', $approvalFlow->company_id)
                    ->where('id', '!=', $approvalFlow->id)
                    ->where('is_default', true)
                    ->update([
                        'is_default' => false,
                    ]);
            }

            $approvalFlow->update($data);
        });

        return response()->json([
            'success' => true,
            'message' => 'Approval Flow berhasil diperbarui',
            'data' => $approvalFlow->fresh()->load([
                'company',
                'branch',
                'department',
            ]),
        ]);
    }

    public function destroy(ApprovalFlow $approvalFlow)
    {
        $approvalFlow->delete();

        return response()->json([
            'success' => true,
            'message' => 'Approval Flow berhasil dihapus',
            'data' => null,
        ]);
    }
}