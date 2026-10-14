<?php

namespace App\Enums;

enum OptionRuleAction: string
{
    case Blocks = 'blocks';
    case Requires = 'requires';
}
