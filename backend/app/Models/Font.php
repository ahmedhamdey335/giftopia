<?php

namespace App\Models;

use App\Models\Concerns\HasOptionRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Font extends Model
{
    use HasFactory;
    use HasOptionRules;

    protected $fillable = [
        'name',
        'price_delta',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'integer',
        ];
    }
}
