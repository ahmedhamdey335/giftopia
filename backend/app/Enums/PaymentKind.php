<?php

namespace App\Enums;

enum PaymentKind: string
{
    case Deposit = 'deposit';
    case Balance = 'balance';
    case Refund = 'refund';
}
