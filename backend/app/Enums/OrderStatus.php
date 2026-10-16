<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case AwaitingPayment = 'awaiting_payment';
}
