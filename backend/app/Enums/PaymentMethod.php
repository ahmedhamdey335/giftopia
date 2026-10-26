<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case Wallet = 'wallet';
}
