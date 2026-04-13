<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceAddonGroup extends Model
{
    protected $fillable = [
        'service_id',
        'label',
        'slug',
        'input_type',
        'helper_text',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ServiceAddon::class)->orderBy('sort_order');
    }
}
