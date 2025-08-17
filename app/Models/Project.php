<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'extra_fields',
        'with_agent',
    ];

    protected $casts = [
        'extra_fields' => 'array',
        'total_price' => 'decimal:2',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
