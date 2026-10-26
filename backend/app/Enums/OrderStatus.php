<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingDeposit = 'awaiting_deposit';
    case Confirmed = 'confirmed';
    case AwaitingBalance = 'awaiting_balance';
    case ReadyForDelivery = 'ready_for_delivery';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
