<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'video_link',
        'thumbnail_url',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function activeServices(): HasMany
    {
        return $this->hasMany(Service::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function addonAssignments(): MorphMany
    {
        return $this->morphMany(ServiceAddonAssignment::class, 'assignable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
