<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proof extends Model
{
    protected $fillable = [
        'order_item_id',
        'version',
        'text',
        'font_name',
        'text_color_name',
        'note',
        'preview_asset_id',
        'status',
        'requested_at',
        'responded_at',
        'response_note',
        'auto_approve_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'auto_approve_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
