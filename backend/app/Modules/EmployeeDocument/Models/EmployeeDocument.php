<?php

namespace App\Modules\EmployeeDocument\Models;

use App\Models\User;
use App\Modules\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EmployeeDocument extends Model
{
    /**
     * Kategori bebas divalidasi via StoreEmployeeDocumentRequest, sengaja
     * TIDAK dibuat tabel master terpisah -- daftar kategori dokumen
     * kependudukan/kepegawaian jarang berubah, list konstan cukup.
     */
    public const CATEGORIES = [
        'ktp', 'npwp', 'kartu_keluarga', 'ijazah', 'kontrak_kerja', 'skck', 'lainnya',
    ];

    protected $fillable = [
        'employee_id',
        'category',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by_user_id',
        'notes',
    ];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
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