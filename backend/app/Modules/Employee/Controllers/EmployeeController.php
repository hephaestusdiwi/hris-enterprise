<?php

namespace App\Modules\Employee\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Employee\Contracts\EmployeeHierarchyServiceInterface;
use App\Modules\Employee\Contracts\EmployeeScopeInterface;
use App\Modules\Employee\Exports\EmployeeExport;
use App\Modules\Employee\Imports\EmployeeBulkUpdateImport;
use App\Modules\Employee\Imports\EmployeeImport;
use App\Modules\Employee\Models\Employee;
use Illuminate\Http\Request;
use App\Modules\Employee\Requests\StoreEmployeeRequest;
use App\Modules\Employee\Requests\UpdateEmployeeRequest;
use App\Modules\Employee\Services\EmployeeService;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    protected array $relations = ['company', 'branch', 'department', 'position', 'jobLevel', 'workingSchedule', 'employmentStatus', 'employmentType', 'manager', 'user'];

    public function __construct(
        private EmployeeService $employeeService,
        private EmployeeScopeInterface $employeeScope,
        private EmployeeHierarchyServiceInterface $hierarchy,
    ){
    }

    public function index(Request $request)
    {
        $employees = $this->employeeScope
            ->apply(
                Employee::with($this->relations)->latest(),
                $request->user(),
            )
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employees,
        ]);
    }

    public function availableUsers()
    {
        $linkedUserIds = Employee::whereNotNull('user_id')->pluck('user_id');

        $users = User::select('id', 'name', 'email')
            ->whereNotIn('id', $linkedUserIds)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $users,
        ]);
    }

    public function nextNumber()
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ['employee_number' => $this->generateEmployeeNumber()],
        ]);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $result = $this->employeeService->createWithUserAccount($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil dibuat',
            'data' => [
                ...$result['employee']->load($this->relations)->toArray(),
                'invite_link' => $result['invite_link'],
            ],
        ], 201);
    }

    /**
     * Export data employee sesuai scope existing (EmployeeScope -- sama
     * persis visibility yang dipakai index()). Hasil export ini JUGA yang
     * dipakai sebagai template "Bulk Update" (export, edit di Excel,
     * upload lagi ke bulkUpdate()).
     */
    public function export(Request $request)
    {
        $employees = $this->employeeScope
            ->apply(
                Employee::with(['company', 'branch', 'department', 'position', 'jobLevel', 'employmentType', 'employmentStatus', 'manager', 'user']),
                $request->user(),
            )
            ->when($request->query('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->query('branch_id'), fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->query('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->get();

        return Excel::download(new EmployeeExport($employees), 'employees-'.now()->format('Y-m-d').'.xlsx');
    }

    /**
     * Template kosong (headers doang) buat "Bulk Add Employee" -- beda dari
     * export() yang isinya data existing.
     */
    public function importTemplate()
    {
        return Excel::download(new EmployeeExport(Employee::query()->whereRaw('1 = 0')->get()), 'employee-import-template.xlsx');
    }

    /**
     * "Bulk Add Employee" -- import karyawan BARU dari Excel/CSV. Setiap
     * baris valid diproses lewat EmployeeService::createWithUserAccount()
     * yang sama dengan store() -- tidak ada logic duplikat.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:10240'],
        ]);

        $import = new EmployeeImport($this->employeeService);
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => count($import->created).' employee berhasil dibuat, '.count($import->errors).' baris gagal.',
            'data' => [
                'created' => $import->created,
                'errors' => $import->errors,
            ],
        ], 201);
    }

    /**
     * "Bulk Update Data" -- update employee yang SUDAH ADA (dicocokkan
     * employee_number). Field lifecycle-controlled (company/branch/dst)
     * TETAP dijaga lewat guard yang sama persis dengan UpdateEmployeeRequest
     * -- lihat EmployeeBulkUpdateImport untuk detail.
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,xls', 'max:10240'],
        ]);

        $import = new EmployeeBulkUpdateImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'success' => true,
            'message' => count($import->updated).' employee berhasil diupdate, '.count($import->errors).' baris gagal/ditolak.',
            'data' => [
                'updated' => $import->updated,
                'errors' => $import->errors,
            ],
        ]);
    }

    public function resendInvite(Employee $employee)
    {
        $inviteLink = $this->employeeService->resendInvite($employee);

        return response()->json([
            'success' => true,
            'message' => 'Invite link berhasil digenerate ulang. Link lama sudah tidak berlaku.',
            'data' => ['invite_link' => $inviteLink],
        ]);
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employee->load($this->relations),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $employee->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil diperbarui',
            'data' => $employee->load($this->relations),
        ]);
    }

    public function destroy(Employee $employee)
    {
        $this->authorize('delete', $employee);
        
        $employee->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employee berhasil dihapus',
            'data' => null,
        ]);
    }

    public static function generateEmployeeNumber(): string
    {
        $year = date('Y');
        $prefix = "EMP-{$year}-";

        $last = Employee::withTrashed()
            ->where('employee_number', 'like', $prefix.'%')
            ->orderByDesc('employee_number')
            ->first();

        $next = 1;
        if ($last) {
            $next = (int) substr($last->employee_number, strlen($prefix)) + 1;
        }

        return $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    // cuma buat foto
    public function orgChart(Request $request)
    {
        $user = $request->user();

        // Admin/hr: full company org chart (behavior lama, tidak berubah).
        // Selain itu: SCOPED ke diri sendiri + subordinate tree-nya sendiri
        // (bug lama: endpoint ini dulu selalu return seluruh company tanpa
        // scoping sama sekali — ditemukan saat integrasi frontend hierarchy).
        if ($user->hasRole(['admin', 'hr'])) {
            $employees = Employee::with(['position:id,name'])
            ->whereNull('resign_date')
            ->get([
                'id',
                'first_name',
                'last_name',
                'manager_employee_id',
                'position_id',
                'photo_path',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OK',
                'data' => $this->buildOrgTree($employees, null),
            ]);
        }

        $actingEmployee = $user->employee;

        if (! $actingEmployee) {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => []]);
        }

        $visibleIds = $this->hierarchy->visibleEmployeeIds($actingEmployee);

        $employees = Employee::with(['position:id,name'])
        ->whereNull('resign_date')
        ->whereIn('id', $visibleIds)
        ->get([
            'id',
            'first_name',
            'last_name',
            'manager_employee_id',
            'position_id',
            'photo_path',
        ]);

        // Root pohonnya diri sendiri (bukan manager_employee_id => null, karena
        // manager si actor sendiri ada di LUAR scope-nya dan memang tidak boleh
        // ikut muncul).
        $self = $employees->firstWhere('id', $actingEmployee->id);

        if (! $self) {
            return response()->json(['success' => true, 'message' => 'OK', 'data' => []]);
        }

        $tree = [
            'id' => $self->id,
            'name' => trim("{$self->first_name} {$self->last_name}"),
            'position' => $self->position?->name,
            'photo_url' => $self->photo_url,
            'children' => $this->buildOrgTree($employees, $self->id),
        ];

        return response()->json(['success' => true, 'message' => 'OK', 'data' => [$tree]]);
    }

    /**
     * Manager + Direct Reports employee tertentu, buat kebutuhan Employee
     * Detail. Object-level authorization numpang EmployeePolicy::view() yang
     * sudah ada (sama seperti show()) — tidak ada Policy baru.
     */
    public function hierarchy(Employee $employee)
    {
        $this->authorize('view', $employee);

        $manager = $employee->manager()
            ->with(['position:id,name'])
            ->first();

        $directReports = $this->hierarchy
            ->directReports($employee)
            ->load('position:id,name');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'manager' => $manager ? [
                    'id' => $manager->id,
                    'name' => trim("{$manager->first_name} {$manager->last_name}"),
                    'position' => $manager->position?->name,
                    'photo_url' => $manager->photo_url,
                ] : null,

                'direct_reports' => $directReports
                    ->map(fn (Employee $e) => [
                        'id' => $e->id,
                        'name' => trim("{$e->first_name} {$e->last_name}"),
                        'position' => $e->position?->name,
                        'photo_url' => $e->photo_url,
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Company Org Chart -- self-service, TANPA permission gate. Beda dari
     * orgChart() di atas yang di belakang 'view employees' dan buat non-
     * admin/hr di-scope ke subordinate tree sendiri: ini SENGAJA nampilin
     * SELURUH company, field terbatas (nama, foto, posisi, department),
     * sama seperti People Directory di bawah -- setiap employee yang login
     * berhak lihat siapa aja di perusahaan & struktur reporting-nya, ala
     * Mekari Talenta. orgChart() yang lama TIDAK diubah/disentuh.
     */
    public function companyOrgChart(Request $request)
    {
        $employees = Employee::with(['position:id,name', 'department:id,name'])
            ->whereNull('resign_date')
            ->get(['id', 'first_name', 'last_name', 'manager_employee_id', 'position_id', 'department_id', 'photo_path']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $this->buildDirectoryTree($employees, null),
        ]);
    }

    /**
     * People Directory -- self-service, TANPA permission gate. List datar
     * (bukan tree) semua employee aktif, field terbatas: nama, foto,
     * posisi, department, nama manager. TIDAK ada NIK/rekening/kontak
     * pribadi/data sensitif lain -- itu semua tetap di balik
     * EmployeeDocument/CompanyDocument yang permission-nya ketat.
     */
    public function directory(Request $request)
    {
        $employees = Employee::with(['position:id,name', 'department:id,name', 'manager:id,first_name,last_name'])
            ->whereNull('resign_date')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'manager_employee_id', 'position_id', 'department_id', 'photo_path']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $employees->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => trim("{$e->first_name} {$e->last_name}"),
                'photo_url' => $e->photo_url,
                'position' => $e->position?->name,
                'department' => $e->department?->name,
                'manager_name' => $e->manager ? trim("{$e->manager->first_name} {$e->manager->last_name}") : null,
            ])->values(),
        ]);
    }

    /**
     * Detail read-only 1 orang buat People Directory -- self-service,
     * TANPA permission gate, field SAMA PERSIS kayak directory() (tidak
     * ada field tambahan yang lebih sensitif). Dipakai waktu klik
     * card/node di People Directory & Company Org Chart, BUKAN pengganti
     * EmployeeDetailView (halaman admin, tetap digembok 'view employees').
     */
    public function directoryShow(Employee $employee)
    {
        abort_if($employee->resign_date !== null, 404);

        $employee->load(['position:id,name', 'department:id,name', 'manager:id,first_name,last_name']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'photo_url' => $employee->photo_url,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
                'manager_name' => $employee->manager ? trim("{$employee->manager->first_name} {$employee->manager->last_name}") : null,
            ],
        ]);
    }

    /**
     * Sama seperti buildOrgTree() tapi nyisipin department -- dibuat
     * terpisah (bukan nambahin field ke buildOrgTree yang sudah ada)
     * supaya response shape endpoint admin lama (orgChart()) sama sekali
     * tidak berubah.
     */
    private function buildDirectoryTree($employees, ?int $managerId = null): array
    {
        return $employees
            ->where('manager_employee_id', $managerId)
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
                'photo_url' => $employee->photo_url,
                'children' => $this->buildDirectoryTree($employees, $employee->id),
            ])
            ->values()
            ->all();
    }

    private function buildOrgTree($employees, ?int $managerId = null): array
    {
        return $employees
            ->where('manager_employee_id', $managerId)
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'name' => trim("{$employee->first_name} {$employee->last_name}"),
                'position' => $employee->position?->name,
                'photo_url' => $employee->photo_url,
                'children' => $this->buildOrgTree($employees, $employee->id),
            ])
            ->values()
            ->all();
    }
}