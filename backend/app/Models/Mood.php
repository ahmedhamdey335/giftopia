<?php

namespace App\Models;

use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mood extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'name',
        'preset_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'preset_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
