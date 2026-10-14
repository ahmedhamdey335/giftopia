<?php

namespace App\Models;

use App\Enums\ColorSwatchRole;
use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ColorSwatch extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'name',
        'hex',
        'role',
        'price_delta',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'role' => ColorSwatchRole::class,
            'price_delta' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_available', true);
    }
}
