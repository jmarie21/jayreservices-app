<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ServiceAddonAssignment extends Model
{
    protected $fillable = [
        'service_addon_id',
        'assignable_type',
        'assignable_id',
        'client_price_override',
        'editor_price_override',
    ];

    protected function casts(): array
    {
        return [
            'client_price_override' => 'decimal:2',
            'editor_price_override' => 'decimal:2',
        ];
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(ServiceAddon::class, 'service_addon_id');
    }

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }
}
