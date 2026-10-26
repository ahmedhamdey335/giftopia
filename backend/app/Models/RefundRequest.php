<?php

namespace App\Models;

use App\Enums\RefundRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'reason',
        'status',
        'requested_amount',
        'approved_amount',
        'decision_note',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundRequestStatus::class,
            'requested_amount' => 'integer',
            'approved_amount' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
