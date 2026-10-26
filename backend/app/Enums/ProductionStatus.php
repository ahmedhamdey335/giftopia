<?php

namespace App\Enums;

enum ProductionStatus: string
{
    case Placed = 'placed';
    case AwaitingProof = 'awaiting_proof';
    case ReadyToStart = 'ready_to_start';
    case InProduction = 'in_production';
    case Curing = 'curing';
    case QualityCheck = 'qc';
    case Packed = 'packed';
}
