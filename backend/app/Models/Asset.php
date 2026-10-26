<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use HasUuids;

    protected $fillable = [
        'kind',
        'owner_user_id',
        'order_item_id',
        'refund_request_id',
        'disk',
        'path',
        'mime',
        'size',
        'original_name',
        'attached_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'attached_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
