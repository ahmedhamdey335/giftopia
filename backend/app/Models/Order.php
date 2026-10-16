<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'currency',
        'subtotal',
        'total',
        'price_breakdown',
        'shipping_name',
        'shipping_phone',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_city',
        'shipping_governorate',
        'shipping_postal_code',
        'shipping_country',
        'payment_method',
        'payment_status',
        'ready_by',
        'accepted_terms_at',
        'gift_wrap',
        'gift_message',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'integer',
            'total' => 'integer',
            'price_breakdown' => 'array',
            'ready_by' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'gift_wrap' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function productionEvents(): HasMany
    {
        return $this->hasMany(ProductionEvent::class);
    }
}
