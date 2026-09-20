<?php
 
namespace App\Modules\CompanyDocument\Models;
 
use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
 
/**
 * Generic document module -- pusat buat 2 konsep:
 *
 * - Open/Public Document: sop, handbook, work_guide, form_template.
 *   Company-wide (employee_id null) ATAU opsional terikat ke 1 employee
 *   (mis. template kontrak yang di-generate khusus dia), tapi tetap
 *   "public" -- siapa pun yang punya akses ke module ini bisa lihat.
 * - Hidden/Private Document: ktp, bank_account, employment_contract,
 *   disciplinary. WAJIB terikat ke 1 employee, dan requires-employee()
 *   menegaskan itu. Permission buat LIHAT tiap kategori FIXED di sini
 *   (view_permission) -- TIDAK PERNAH diinput bebas oleh uploader lewat
 *   request, supaya dokumen sensitif gak bisa "dibuka" cuma karena
 *   yang upload salah pilih permission.
 */
class CompanyDocument extends Model
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_PRIVATE = 'private';

    public const CATEGORIES = [
        'sop' => [
            'visibility' => self::VISIBILITY_PUBLIC,
            'view_permission' => 'view company documents',
        ],
        'handbook' => [
            'visibility' => self::VISIBILITY_PUBLIC,
            'view_permission' => 'view company documents',
        ],
        'work_guide' => [
            'visibility' => self::VISIBILITY_PUBLIC,
            'view_permission' => 'view company documents',
        ],
        'form_template' => [
            'visibility' => self::VISIBILITY_PUBLIC,
            'view_permission' => 'view company documents',
        ],
        'ktp' => [
            'visibility' => self::VISIBILITY_PRIVATE,
            'view_permission' => 'view identity documents',
        ],
        'bank_account' => [
            'visibility' => self::VISIBILITY_PRIVATE,
            'view_permission' => 'view financial documents',
        ],
        'employment_contract' => [
            'visibility' => self::VISIBILITY_PRIVATE,
            'view_permission' => 'view contract documents',
        ],
        'disciplinary' => [
            'visibility' => self::VISIBILITY_PRIVATE,
            'view_permission' => 'view disciplinary documents',
        ],
    ];

    public const PUBLIC_CATEGORIES = ['sop', 'handbook', 'work_guide', 'form_template'];

    public const PRIVATE_CATEGORIES = ['ktp', 'bank_account', 'employment_contract', 'disciplinary'];

    protected $fillable = [
        'employee_id',
        'category',
        'visibility',
        'module_content',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by_user_id',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public static function visibilityFor(string $category): string
    {
        return self::CATEGORIES[$category]['visibility'];
    }

    public static function viewPermissionFor(string $category): string
    {
        return self::CATEGORIES[$category]['view_permission'];
    }

    /**
     * Manage (create/edit/delete): kategori public pakai permission
     * per-aksi standar ('create/edit/delete company documents'), semua
     * kategori private digabung 1 permission 'manage private documents'
     * -- yang perlu fixed per kategori itu VIEW-nya, bukan siapa yang
     * boleh upload.
     */
    public static function managePermissionFor(string $category, string $action): string
    {
        if (in_array($category, self::PRIVATE_CATEGORIES, true)) {
            return 'manage private documents';
        }

        return match ($action) {
            'create' => 'create company documents',
            'edit' => 'edit company documents',
            'delete' => 'delete company documents',
        };
    }

    public static function requiresEmployee(string $category): bool
    {
        return in_array($category, self::PRIVATE_CATEGORIES, true);
    }
 
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
 
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
 
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}