<?php

namespace App\Modules\Training\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Training\Contracts\TrainingScopeInterface;
use App\Modules\Training\Enums\TrainingProgramStatus;
use App\Modules\Training\Models\TrainingProgram;
use App\Modules\Training\Requests\StoreTrainingProgramRequest;
use App\Modules\Training\Requests\UpdateTrainingProgramRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingProgramController extends Controller
{
    public function __construct(private TrainingScopeInterface $scope)
    {
    }

    // ---------- Management (permission 'view/create/edit/delete trainings') ----------

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', TrainingProgram::class);

        $programs = TrainingProgram::query()
            ->withCount('sessions')
            ->with(['company', 'category', 'pic'])
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('training_category_id'), fn ($q, $v) => $q->where('training_category_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $programs]);
    }

    public function store(StoreTrainingProgramRequest $request): JsonResponse
    {
        $this->authorize('create', TrainingProgram::class);

        $program = TrainingProgram::create([
            ...$request->validated(),
            'status' => TrainingProgramStatus::Draft,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Training Program berhasil dibuat',
            'data' => $program->load(['company', 'category', 'pic']),
        ], 201);
    }

    public function show(TrainingProgram $trainingProgram): JsonResponse
    {
        $trainingProgram->load('recipients');
        $this->authorize('view', $trainingProgram);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $trainingProgram->load([
                'company', 'category', 'pic.user', 'createdBy', 'recipients.user',
                'sessions' => fn ($q) => $q->withCount('participants')->orderBy('start_at'),
            ]),
        ]);
    }

    public function update(UpdateTrainingProgramRequest $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $this->authorize('update', $trainingProgram);

        $trainingProgram->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Training Program berhasil diperbarui',
            'data' => $trainingProgram->fresh()->load(['company', 'category', 'pic']),
        ]);
    }

    public function destroy(TrainingProgram $trainingProgram): JsonResponse
    {
        $this->authorize('delete', $trainingProgram);

        $trainingProgram->delete();

        return response()->json(['success' => true, 'message' => 'Training Program berhasil dihapus', 'data' => null]);
    }

    // ---------- Self-service (read-only) ----------

    public function indexMine(Request $request): JsonResponse
    {
        $programs = $this->scope
            ->applyMine(TrainingProgram::query(), $request->user())
            ->with(['company', 'category', 'pic'])
            ->withCount('sessions')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'message' => 'OK', 'data' => $programs]);
    }
}