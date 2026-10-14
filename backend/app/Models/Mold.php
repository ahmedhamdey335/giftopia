<?php

namespace App\Models;

use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mold extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'base_item_id',
        'name',
        'size_label',
        'depth_mm',
        'price_delta',
    ];

    protected function casts(): array
    {
        return [
            'depth_mm' => 'decimal:2',
            'price_delta' => 'integer',
        ];
    }

    public function baseItem(): BelongsTo
    {
        return $this->belongsTo(BaseItem::class);
    }
}
