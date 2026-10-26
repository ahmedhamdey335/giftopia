<?php

namespace App\Services\Payments;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use LogicException;

final class PaymobGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'paymob';
    }

    public function createPayment(Order $order, PaymentKind $kind, PaymentMethod $method): PaymentIntent
    {
        throw new LogicException('Paymob checkout is not enabled until the account-specific API integration is verified.');
    }

    public function parseWebhook(Request $request): PaymentEvent
    {
        throw new LogicException('Paymob webhook verification is not enabled until its official signature specification is verified.');
    }

    public function refund(Payment $payment, int $amount): RefundResult
    {
        throw new LogicException('Paymob refunds are not enabled until the account-specific API integration is verified.');
    }
}
