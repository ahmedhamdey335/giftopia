<?php

namespace App\Models;

use App\Enums\ProductionStatus;
use App\Enums\ProofStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'proof_status',
        'checklist',
        'needs_attention',
        'attention_note',
        'started_at',
        'cure_started_at',
        'qc_at',
        'packed_at',
        'actual_labor_minutes',
        'customization',
    ];

    protected function casts(): array
    {
        return [
            'has_text' => 'boolean',
            'labor_minutes_total' => 'integer',
            'unit_price' => 'integer',
            'production_status' => ProductionStatus::class,
            'proof_status' => ProofStatus::class,
            'checklist' => 'array',
            'needs_attention' => 'boolean',
            'started_at' => 'datetime',
            'cure_started_at' => 'datetime',
            'qc_at' => 'datetime',
            'packed_at' => 'datetime',
            'actual_labor_minutes' => 'integer',
            'customization' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
