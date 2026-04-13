<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportMessageAttachment extends Model
{
    protected $fillable = [
        'support_message_id',
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
    ];

    protected $visible = [
        'id',
        'url',
        'mime_type',
        'original_name',
        'size_bytes',
        'position',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }

    public function getUrlAttribute(): string
    {
        return match ($this->disk) {
            'public_path' => asset($this->path),
            'public' => Storage::disk('public')->url($this->path),
            default => Storage::disk('s3')->url($this->path),
        };
    }
}
