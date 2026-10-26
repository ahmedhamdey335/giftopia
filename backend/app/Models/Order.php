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
        'delivery_fee',
        'total',
        'deposit_amount',
        'balance_amount',
        'amount_paid',
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
        'deposit_paid_at',
        'balance_paid_at',
        'delivered_at',
        'deposit_expires_at',
        'cancelled_at',
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
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'deposit_amount' => 'integer',
            'balance_amount' => 'integer',
            'amount_paid' => 'integer',
            'price_breakdown' => 'array',
            'ready_by' => 'datetime',
            'deposit_paid_at' => 'datetime',
            'balance_paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'deposit_expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
