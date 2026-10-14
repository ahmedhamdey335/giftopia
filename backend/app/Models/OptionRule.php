<?php

namespace App\Models;

use App\Enums\OptionRuleAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class OptionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'rule',
        'target_type',
        'target_id',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'rule' => OptionRuleAction::class,
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
