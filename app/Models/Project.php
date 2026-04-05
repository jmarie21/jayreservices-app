<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    public const EDITOR_ALLOWED_TRANSITIONS = [
        'backlog' => ['todo', 'in_progress', 'for_qa', 'done_qa', 'sent_to_client'],
        'todo' => ['in_progress', 'for_qa', 'done_qa', 'sent_to_client'],
        'in_progress' => ['for_qa', 'done_qa', 'sent_to_client'],
        'for_qa' => ['done_qa', 'sent_to_client'],
        'done_qa' => ['sent_to_client'],
        'sent_to_client' => [],
        'revision' => ['revision_completed', 'sent_to_client'],
        'revision_completed' => ['sent_to_client'],
        'cancelled' => [],
    ];

    protected $fillable = [
        'client_id',
        'editor_id',
        'service_id',
        'service_sub_style_id',
        'style',
        'company_name',
        'contact',
        'project_name',
        'format',
        'camera',
        'quality',
        'music',
        'music_link',
        'file_link',
        'notes',
        'total_price',
        'output_link',
        'status',
        'priority',
        'extra_fields',
        'with_agent',
        'editor_price',
        'per_property',
        'per_property_count',
        'rush',
        'in_progress_since',
        'revision_since',
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'output_link' => 'array',
        'total_price' => 'decimal:2',
        'editor_price' => 'decimal:2',
        'with_agent' => 'boolean',
        'per_property' => 'boolean',
        'per_property_count' => 'integer',
        'rush' => 'boolean',
        'in_progress_since' => 'datetime',
        'revision_since' => 'datetime',
    ];

    public function getStallDeadlineHours(): int
    {
        $serviceName = $this->service?->name ?? '';
        $isRush = (bool) $this->rush;

        if (str_contains($serviceName, 'Luxury')) {
            return $isRush ? 18 : 36;
        }

        if (str_contains($serviceName, 'Premium')) {
            return $isRush ? 12 : 24;
        }

        return $isRush ? 6 : 12;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function subStyle(): BelongsTo
    {
        return $this->belongsTo(ServiceSubStyle::class, 'service_sub_style_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_project')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }
}
