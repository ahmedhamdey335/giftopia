<?php

namespace App\Services\Payments;

interface PaymentGateway
{
    public function initialize(string $paymentMethod): PaymentGatewayResult;
}
