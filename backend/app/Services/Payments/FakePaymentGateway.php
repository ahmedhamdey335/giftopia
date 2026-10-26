<?php

namespace App\Services\Payments;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Exceptions\InvalidPaymentWebhook;
use App\Exceptions\InvalidWebhookSignature;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;
use LogicException;

final class FakePaymentGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'fake';
    }

    public function createPayment(Order $order, PaymentKind $kind, PaymentMethod $method): PaymentIntent
    {
        $amount = match ($kind) {
            PaymentKind::Deposit => $order->deposit_amount,
            PaymentKind::Balance => $order->balance_amount,
            PaymentKind::Refund => throw new LogicException('Refunds cannot create a checkout payment intent.'),
        };
        $reference = (string) Str::uuid();
        $baseUrl = rtrim((string) config('app.frontend_url', ''), '/');

        return new PaymentIntent(
            reference: $reference,
            redirectUrl: $baseUrl.'/payments/fake?reference='.rawurlencode($reference),
            amount: $amount,
        );
    }

    public function parseWebhook(Request $request): PaymentEvent
    {
        $secret = (string) config('services.payments.fake_webhook_secret', '');
        $signature = (string) $request->header('X-Fake-Signature', '');
        if ($secret === '' || $signature === ''
            || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            throw new InvalidWebhookSignature('The fake payment webhook signature is invalid.');
        }

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidPaymentWebhook('The payment webhook body is not valid JSON.', previous: $exception);
        }

        if (! is_array($payload)
            || ! is_string($payload['reference'] ?? null)
            || ! is_string($payload['status'] ?? null)
            || ! in_array($payload['status'], ['succeeded', 'failed', 'cancelled'], true)
            || ! is_int($payload['amount'] ?? null)
            || $payload['amount'] < 0) {
            throw new InvalidPaymentWebhook('The payment webhook payload is invalid.');
        }

        return new PaymentEvent(
            reference: $payload['reference'],
            status: PaymentRecordStatus::from($payload['status']),
            amount: $payload['amount'],
            raw: $payload,
        );
    }

    public function refund(Payment $payment, int $amount): RefundResult
    {
        if ($amount < 1 || $amount > $payment->amount) {
            return new RefundResult(false, null, 'Refund amount is outside the payment amount.');
        }

        return new RefundResult(true, (string) Str::uuid());
    }
}
