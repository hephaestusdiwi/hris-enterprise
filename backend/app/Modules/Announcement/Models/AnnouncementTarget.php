<?php

namespace App\Modules\Announcement\Models;

use App\Modules\Announcement\Enums\AnnouncementTargetCriteriaType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementTarget extends Model
{
    protected $fillable = ['announcement_id', 'target_type', 'target_id'];

    protected function casts(): array
    {
        return ['target_type' => AnnouncementTargetCriteriaType::class];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
