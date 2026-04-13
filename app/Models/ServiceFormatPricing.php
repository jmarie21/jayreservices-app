<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFormatPricing extends Model
{
    protected $table = 'service_format_pricing';

    protected $fillable = [
        'service_sub_style_id',
        'format_name',
        'format_label',
        'client_price',
        'editor_price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'client_price' => 'decimal:2',
            'editor_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function subStyle(): BelongsTo
    {
        return $this->belongsTo(ServiceSubStyle::class, 'service_sub_style_id');
    }
}
