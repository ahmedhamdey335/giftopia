<?php

namespace App\Services\Payments;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function provider(): string;

    public function createPayment(Order $order, PaymentKind $kind, PaymentMethod $method): PaymentIntent;

    public function parseWebhook(Request $request): PaymentEvent;

    public function refund(Payment $payment, int $amount): RefundResult;
}
