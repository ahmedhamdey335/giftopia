<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;

final readonly class PaymentGatewayResult
{
    public function __construct(
        public OrderStatus $orderStatus,
        public PaymentStatus $paymentStatus,
    ) {}
}
