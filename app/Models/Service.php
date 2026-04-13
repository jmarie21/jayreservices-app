<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'features',
        'price',
        'video_link',
        'thumbnail_url',
        'service_category_id',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function subStyles(): HasMany
    {
        return $this->hasMany(ServiceSubStyle::class)->orderBy('sort_order');
    }

    public function addonAssignments(): MorphMany
    {
        return $this->morphMany(ServiceAddonAssignment::class, 'assignable');
    }

    public function addonGroups(): HasMany
    {
        return $this->hasMany(ServiceAddonGroup::class)->orderBy('sort_order');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
