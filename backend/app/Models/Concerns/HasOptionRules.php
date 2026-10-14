<?php

namespace App\Models\Concerns;

use App\Models\OptionRule;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasOptionRules
{
    public function subjectRules(): MorphMany
    {
        return $this->morphMany(OptionRule::class, 'subject');
    }

    public function targetRules(): MorphMany
    {
        return $this->morphMany(OptionRule::class, 'target');
    }
}
