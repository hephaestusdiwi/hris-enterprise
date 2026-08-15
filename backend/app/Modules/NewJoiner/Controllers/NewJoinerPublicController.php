<?php

namespace App\Modules\NewJoiner\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\NewJoiner\Models\NewJoiner;
use App\Modules\NewJoiner\Requests\SubmitNewJoinerRequest;
use App\Modules\NewJoiner\Services\NewJoinerService;
use Illuminate\Http\JsonResponse;

class NewJoinerPublicController extends Controller
{
    public function __construct(private NewJoinerService $service) {}

    public function show(string $token): JsonResponse
    {
        $newJoiner = NewJoiner::where('token', $token)->firstOrFail();

        return response()->json(['success' => true, 'data' => [
            'full_name' => $newJoiner->candidate->full_name,
            'status' => $newJoiner->status->value,
            'expires_at' => $newJoiner->expires_at,
        ]]);
    }

    public function store(string $token, SubmitNewJoinerRequest $request): JsonResponse
    {
        $newJoiner = NewJoiner::where('token', $token)->firstOrFail();
        $newJoiner = $this->service->submit($newJoiner, $request->validated());

        return response()->json(['success' => true, 'message' => 'Data berhasil disubmit.', 'data' => ['status' => $newJoiner->status->value]]);
    }
}