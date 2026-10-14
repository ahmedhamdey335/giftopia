<?php

namespace App\Models;

use App\Enums\InsertType;
use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insert extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'type',
        'name',
        'color',
        'price',
        'labor_minutes',
        'min_depth_mm',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'type' => InsertType::class,
            'price' => 'integer',
            'labor_minutes' => 'integer',
            'min_depth_mm' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }
}
