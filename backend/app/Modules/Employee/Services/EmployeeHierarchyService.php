<?php

namespace App\Modules\Employee\Services;

use App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface;
use App\Modules\Employee\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Implementasi berbasis manager_employee_id (satu-satunya source of truth
 * current reporting-line — lihat Employee Movement untuk history-nya).
 *
 * Descendant tree dihitung dengan cara yang sama seperti
 * EmployeeController::orgChart() yang sudah ada: tarik seluruh pasangan
 * (id, manager_employee_id) SEKALI, bangun adjacency map di PHP, lalu BFS.
 * Konsisten dengan pola yang sudah dipakai project ini, bukan raw recursive
 * CTE — cukup untuk skala HRIS ini, dan gampang diaudit/di-test.
 */
class EmployeeHierarchyService implements EmployeeHierarchyServiceInterface
{
    public function directReports(Employee $manager): Collection
    {
        return $manager->subordinates()->get();
    }

    public function descendants(Employee $manager): Collection
    {
        $ids = $this->descendantIds($manager);

        if (empty($ids)) {
            return new Collection();
        }

        return Employee::whereIn('id', $ids)->get();
    }

    public function descendantIds(Employee $manager): array
    {
        $adjacency = $this->buildAdjacencyMap();

        $result = [];
        $queue = [$manager->id];
        $visited = [$manager->id => true];

        while ($queue) {
            $currentId = array_shift($queue);

            foreach ($adjacency[$currentId] ?? [] as $childId) {
                // Guard terhadap data korup (manager chain melingkar) —
                // tanpa ini BFS bisa infinite loop.
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $result[] = $childId;
                $queue[] = $childId;
            }
        }

        return $result;
    }

    public function managerChain(Employee $employee): Collection
    {
        $chain = new Collection();
        $current = $employee->manager;
        $guard = 0;

        // Guard 50 level — proteksi kalau data manager_employee_id korup
        // (circular reference), bukan asumsi org sedalam itu.
        while ($current && $guard < 50) {
            $chain->push($current);
            $current = $current->manager;
            $guard++;
        }

        return $chain;
    }

    public function isInSubordinateTree(Employee $manager, Employee $target): bool
    {
        if ($manager->id === $target->id) {
            return false;
        }

        return $this->managerChain($target)->contains('id', $manager->id);
    }

    public function visibleEmployeeIds(Employee $actor): array
    {
        return array_merge([$actor->id], $this->descendantIds($actor));
    }

    /**
     * @return array<int, array<int, int>> manager_employee_id => [child ids]
     */
    private function buildAdjacencyMap(): array
    {
        $map = [];

        Employee::query()
            ->whereNotNull('manager_employee_id')
            ->pluck('manager_employee_id', 'id')
            ->each(function ($managerId, $id) use (&$map) {
                $map[$managerId][] = $id;
            });

        return $map;
    }
}
