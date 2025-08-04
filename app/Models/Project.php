<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
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
}
