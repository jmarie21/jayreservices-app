<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'client_id',
        'editor_id',
        'service_id',
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
        'rush'
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'total_price' => 'decimal:2',
        'with_agent' => 'boolean',
        'per_property' => 'boolean',
        'per_property_count' => 'integer',
        'rush' => 'boolean'
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "client_id");
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, "editor_id");
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
