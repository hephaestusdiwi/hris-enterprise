<?php

namespace App\Modules\Announcement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AnnouncementAttachment extends Model
{
    protected $fillable = ['announcement_id', 'disk', 'path', 'original_filename', 'mime_type', 'size'];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk($this->disk)->url($this->path) : null;
    }
}
