<?php

namespace App\Modules\Expense\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Expense\Models\ExpensePolicy;
use App\Modules\Expense\Requests\StoreExpensePolicyRequest;
use App\Modules\Expense\Requests\UpdateExpensePolicyRequest;
use Illuminate\Support\Collection;

class ExpensePolicyController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ExpensePolicy::query()
                ->with(['company', 'categories'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(ExpensePolicy $expensePolicy)
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $expensePolicy->load(['company', 'categories']),
        ]);
    }

    public function store(StoreExpensePolicyRequest $request)
    {
        $policy = ExpensePolicy::create([
            ...$request->validated(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        // $policy baru dibuat -> belum ada pivot row sama sekali, jadi
        // existingLimits kosong (setiap category tanpa entry di
        // category_limits otomatis limit_amount = null).
        $policy->categories()->sync($this->buildCategorySyncData(
            $request->validated('category_ids', []),
            $request->validated('category_limits', []),
            collect(),
        ));

        return response()->json([
            'success' => true,
            'message' => 'Expense Policy berhasil dibuat',
            'data' => $policy->load(['company', 'categories']),
        ], 201);
    }

    public function update(
        UpdateExpensePolicyRequest $request,
        ExpensePolicy $expensePolicy,
    ) {
        $expensePolicy->update(
            $request->safe()->except(['category_ids', 'category_limits'])
        );

        // Tidak sync kalau category_ids memang tidak dikirim -- biar
        // categories (dan limit_amount-nya) yang sudah ada tidak ke-reset
        // tanpa sengaja.
        if ($request->has('category_ids')) {
            // limit_amount category yang TETAP ter-attach dan TIDAK
            // disebut ulang di category_limits harus dipertahankan, bukan
            // di-reset ke null -- makanya nilai existing dibaca dulu
            // sebelum sync menimpa pivot row-nya.
            $existingLimits = $expensePolicy->categories()
                ->pluck('expense_policy_category.limit_amount', 'expense_categories.id');

            $expensePolicy->categories()->sync($this->buildCategorySyncData(
                $request->validated('category_ids', []),
                $request->validated('category_limits', []),
                $existingLimits,
            ));
        }

        return response()->json([
            'success' => true,
            'message' => 'Expense Policy berhasil diperbarui',
            'data' => $expensePolicy->fresh()->load(['company', 'categories']),
        ]);
    }

    /**
     * Gabungkan category_ids + category_limits (opsional) jadi array sync
     * pivot lengkap dengan limit_amount. Prioritas nilai limit_amount per
     * category: (1) disebut eksplisit di category_limits, (2) nilai yang
     * sudah ada sebelumnya di pivot ($existingLimits, kosong kalau lagi
     * create policy baru), (3) null (unlimited).
     */
    private function buildCategorySyncData(array $categoryIds, array $categoryLimits, Collection $existingLimits): array
    {
        $limitsByCategory = collect($categoryLimits)->keyBy('expense_category_id');

        return collect($categoryIds)
            ->mapWithKeys(function ($categoryId) use ($limitsByCategory, $existingLimits) {
                $limitAmount = $limitsByCategory->has($categoryId)
                    ? $limitsByCategory->get($categoryId)['limit_amount'] ?? null
                    : $existingLimits->get($categoryId);

                return [$categoryId => ['limit_amount' => $limitAmount]];
            })
            ->all();
    }
}