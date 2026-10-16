<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'mood_id',
        'technique_id',
        'has_text',
        'labor_minutes_total',
        'unit_price',
        'production_status',
        'customization',
    ];

    protected function casts(): array
    {
        return [
            'has_text' => 'boolean',
            'labor_minutes_total' => 'integer',
            'unit_price' => 'integer',
            'production_status' => ProductionStatus::class,
            'customization' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
