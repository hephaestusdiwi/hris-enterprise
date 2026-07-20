<?php

namespace App\Modules\AttendanceSetting\Models;

use App\Modules\Branch\Models\Branch;
use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'require_photo',
        'require_location',
        'office_latitude',
        'office_longtitude',
        'location_radius_meters',
        'allow_mobile_checkin',
    ];

    protected function casts(): array
    {
        return [
            'require_photo' => 'boolean',
            'require_location' => 'boolean',
            'office_latitude' => 'decimal:7',
            'office_longtitude' => 'decimal:7',
            'location_radius_meters' => 'integer',
            'allow_mobile_checkin' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function resolveFor(int $companyId, ?int $branchId = null): ?self
    {
        if ($branchId) {
            $branchSetting = static::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->first();

            if ($branchSetting) {
                return $branchSetting;
            }
        }

        return static::where('company_id', $companyId)
            ->whereNull('branch_id')
            ->first();
    }
}