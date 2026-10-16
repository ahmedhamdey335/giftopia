<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'base_item_id',
        'configuration',
        'price_breakdown',
        'total_price',
        'ready_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'configuration' => 'array',
            'price_breakdown' => 'array',
            'total_price' => 'integer',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function baseItem(): BelongsTo
    {
        return $this->belongsTo(BaseItem::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
