<?php
 
namespace App\Modules\Candidate\Controllers;
 
use App\Http\Controllers\Controller;
use App\Modules\Candidate\Models\Candidate;
use App\Modules\Candidate\Models\CandidateBlacklist;
use App\Modules\Candidate\Requests\StoreCandidateBlacklistRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CandidateBlacklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manageBlacklist', Candidate::class);

        $blacklists = CandidateBlacklist::query()
            ->when(
                $request->string('search')->toString(),
                fn ($q, $v) => $q->where(function ($q2) use ($v) {
                    $q2->where('email', 'like', "%{$v}%")
                        ->orWhere('full_name', 'like', "%{$v}%");
                })
            )
            ->with('blacklistedBy')
            ->latest('blacklisted_at')
            ->paginate();

        return response()->json([
            'success' => true,
            'message' => 'Daftar Candidate Blacklist berhasil diambil',
            'data' => $blacklists,
        ]);
    }

    public function store(StoreCandidateBlacklistRequest $request): JsonResponse
    {
        $this->authorize('manageBlacklist', Candidate::class);

        $data = $request->validated();
        $email = strtolower($data['email']);

        $blacklist = CandidateBlacklist::updateOrCreate(
            ['email' => $email],
            [
                'full_name' => $data['full_name'] ?? null,
                'candidate_id' => $data['candidate_id'] ?? null,
                'reason' => $data['reason'],
                'blacklisted_by_employee_id' => $request->user()->employee?->id,
                'blacklisted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Kandidat berhasil ditambahkan ke Blacklist',
            'data' => $blacklist,
        ], 201);
    }

    public function destroy(CandidateBlacklist $candidateBlacklist): JsonResponse
    {
        $this->authorize('manageBlacklist', Candidate::class);

        $candidateBlacklist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kandidat berhasil dikeluarkan dari Blacklist',
            'data' => null,
        ]);
    }
}