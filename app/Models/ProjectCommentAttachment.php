<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectCommentAttachment extends Model
{
    protected $fillable = [
        'project_comment_id',
        'disk',
        'path',
        'mime_type',
        'original_name',
        'size_bytes',
        'position',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'position' => 'integer',
    ];

    protected $appends = [
        'url',
        'is_legacy',
    ];

    protected $visible = [
        'id',
        'url',
        'mime_type',
        'original_name',
        'size_bytes',
        'position',
        'is_legacy',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(ProjectComment::class, 'project_comment_id');
    }

    public function getUrlAttribute(): string
    {
        return match ($this->disk) {
            'public_path' => asset($this->path),
            'public' => Storage::disk('public')->url($this->path),
            default => Storage::disk('s3')->url($this->path),
        };
    }

    public function getIsLegacyAttribute(): bool
    {
        return false;
    }
}
