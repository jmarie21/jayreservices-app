<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        "name",
        "description",
        "features",
        "price"
    ];

    protected $casts = [
        "features" => "array"
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
