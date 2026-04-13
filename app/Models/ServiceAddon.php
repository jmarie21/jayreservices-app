<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAddon extends Model
{
    protected $fillable = [
        'service_addon_group_id',
        'name',
        'slug',
        'addon_type',
        'client_price',
        'editor_price',
        'has_quantity',
        'is_rush_option',
        'sample_link',
        'group',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'client_price' => 'decimal:2',
            'editor_price' => 'decimal:2',
            'has_quantity' => 'boolean',
            'is_rush_option' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function addonGroup(): BelongsTo
    {
        return $this->belongsTo(ServiceAddonGroup::class, 'service_addon_group_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ServiceAddonAssignment::class);
    }
}
