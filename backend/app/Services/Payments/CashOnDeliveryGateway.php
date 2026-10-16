<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

final class CashOnDeliveryGateway implements PaymentGateway
{
    public function initialize(string $paymentMethod): PaymentGatewayResult
    {
        return new PaymentGatewayResult(
            orderStatus: $paymentMethod === PaymentMethod::CashOnDelivery->value
                ? OrderStatus::Placed
                : OrderStatus::AwaitingPayment,
            paymentStatus: PaymentStatus::Pending,
        );
    }
}
