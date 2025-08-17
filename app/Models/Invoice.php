<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Invoice extends Model
{
    protected $fillable = [
        'client_id',
        'invoice_number',
        'date_from',
        'date_to',
        'total_amount',
        'paypal_link',
        'status',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, "client_id");
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'invoice_project');
    }
}