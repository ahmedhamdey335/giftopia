<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case Placed = 'placed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
