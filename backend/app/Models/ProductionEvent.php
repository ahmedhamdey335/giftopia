<?php

namespace App\Models;

use App\Enums\ProductionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionEvent extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'actor_id',
        'event_type',
        'from_status',
        'to_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ProductionEventType::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
