<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudioEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['session_id', 'step', 'event', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'step' => 'integer'];
    }
}
