<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProjectComment extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'body',
        'image_url',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectCommentAttachment::class)->orderBy('position');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = parent::toArray();
        $attributes['attachments'] = $this->serializeAttachments();

        return $attributes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function serializeAttachments(): array
    {
        $attachments = $this->relationLoaded('attachments')
            ? $this->getRelation('attachments')
            : $this->attachments()->get();

        if ($attachments->isNotEmpty()) {
            return $attachments
                ->values()
                ->map(fn (ProjectCommentAttachment $attachment) => $attachment->toArray())
                ->all();
        }

        $legacyAttachment = $this->legacyAttachmentPayload();

        return $legacyAttachment ? [$legacyAttachment] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function legacyAttachmentPayload(): ?array
    {
        if (! $this->image_url) {
            return null;
        }

        $url = self::resolveLegacyImageUrl($this->image_url);

        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return [
            'id' => 0,
            'url' => $url,
            'mime_type' => null,
            'original_name' => basename((string) $path),
            'size_bytes' => null,
            'position' => 0,
            'is_legacy' => true,
        ];
    }

    public static function resolveLegacyImageUrl(?string $value): ?string
    {
        $path = trim((string) $value);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'public://')) {
            return Storage::disk('public')->url(substr($path, strlen('public://')));
        }

        if (str_starts_with($path, 'public-path://')) {
            return asset(substr($path, strlen('public-path://')));
        }

        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        if (str_starts_with($path, '/chat-comments/')) {
            return asset(ltrim($path, '/'));
        }

        if (str_starts_with($path, 'chat-comments/')) {
            return asset($path);
        }

        if (str_starts_with($path, 's3://')) {
            return Storage::disk('s3')->url(substr($path, strlen('s3://')));
        }

        return Storage::disk('s3')->url($path);
    }
}
