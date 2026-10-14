<?php

namespace App\Models;

use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Technique extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'name',
        'price_delta',
        'labor_minutes',
        'requires_accent',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
            'labor_minutes' => 'integer',
            'requires_accent' => 'boolean',
        ];
    }
}
