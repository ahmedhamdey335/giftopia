<?php

namespace App\Enums;

enum ProductionEventType: string
{
    case Placed = 'placed';
    case DepositReceived = 'deposit_received';
    case PaymentFailed = 'payment_failed';
    case Cancelled = 'cancelled';
}
