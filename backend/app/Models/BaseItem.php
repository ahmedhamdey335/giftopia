<?php

namespace App\Models;

use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BaseItem extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'name',
        'slug',
        'base_price',
        'piece_count',
        'labor_minutes',
        'cure_hours',
        'max_text_chars',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'piece_count' => 'integer',
            'labor_minutes' => 'integer',
            'cure_hours' => 'integer',
            'max_text_chars' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function molds(): HasMany
    {
        return $this->hasMany(Mold::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
