<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionEventType;
use App\Exceptions\CheckoutException;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderNoticeMail;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Production\ProofService;
use App\Services\Production\TransitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionService $transitions,
        private readonly ProofService $proofs,
    ) {}

    public function createAttempt(Order $order, PaymentKind $kind, PaymentMethod $method): Payment
    {
        return DB::transaction(function () use ($order, $kind, $method): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $expectedAmount = match ($kind) {
                PaymentKind::Deposit => $lockedOrder->deposit_amount,
                PaymentKind::Balance => $lockedOrder->balance_amount,
                PaymentKind::Refund => throw new CheckoutException('Refund payments cannot be initiated by customers.'),
            };
            $expectedStatus = $kind === PaymentKind::Deposit
                ? OrderStatus::AwaitingDeposit
                : OrderStatus::AwaitingBalance;
            if ($lockedOrder->status !== $expectedStatus) {
                throw new CheckoutException('This payment is not currently due.', 422, [
                    'kind' => ['The order is not in the required payment state.'],
                ]);
            }
            if ($expectedAmount < 1) {
                throw new CheckoutException('No amount is due for this payment kind.', 422);
            }

            $openAttempt = $lockedOrder->payments()
                ->where('kind', $kind->value)
                ->where('status', PaymentRecordStatus::Pending->value)
                ->latest('id')
                ->first();
            if ($openAttempt !== null && $openAttempt->method === $method) {
                return $openAttempt;
            }
            $openAttempt?->update(['status' => PaymentRecordStatus::Cancelled]);

            $intent = $this->gateway->createPayment($lockedOrder, $kind, $method);
            if ($intent->reference === '' || $intent->amount !== $expectedAmount) {
                throw new \LogicException('The payment gateway returned an invalid payment intent.');
            }

            return $lockedOrder->payments()->create([
                'kind' => $kind,
                'method' => $method,
                'provider' => $this->gateway->provider(),
                'provider_reference' => $intent->reference,
                'amount' => $intent->amount,
                'currency' => $lockedOrder->currency,
                'status' => PaymentRecordStatus::Pending,
                'redirect_url' => $intent->redirectUrl,
            ]);
        });
    }

    public function pendingAttempt(Order $order, PaymentKind $kind): ?Payment
    {
        return $order->payments()
            ->where('kind', $kind->value)
            ->where('status', PaymentRecordStatus::Pending->value)
            ->latest('id')
            ->first();
    }

    public function handleWebhook(string $provider, Request $request): bool
    {
        if ($provider !== $this->gateway->provider()) {
            throw new CheckoutException('Unknown payment provider.', 404);
        }

        $event = $this->gateway->parseWebhook($request);
        $mailOrder = DB::transaction(function () use ($provider, $event): ?array {
            $payment = Payment::query()
                ->where('provider', $provider)
                ->where('provider_reference', $event->reference)
                ->where('kind', '!=', PaymentKind::Refund->value)
                ->lockForUpdate()
                ->first();
            if ($payment === null || $payment->status !== PaymentRecordStatus::Pending) {
                return null;
            }
            if ($event->amount !== $payment->amount) {
                throw new CheckoutException('Payment amount does not match the payment intent.', 422);
            }

            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment->status = $event->status;
            $payment->meta = ['webhook' => $event->raw];
            $sendDepositConfirmation = null;
            $sendBalanceConfirmation = false;
            $refreshDelivery = false;
            if ($event->status === PaymentRecordStatus::Succeeded) {
                $payment->paid_at = now();
                $order->amount_paid += $payment->amount;
                if ($payment->kind === PaymentKind::Deposit) {
                    $order->deposit_paid_at = now();
                    $order->payment_status = $order->balance_amount === 0
                        ? PaymentStatus::Paid
                        : PaymentStatus::DepositPaid;
                    if ($order->balance_amount === 0) {
                        $order->balance_paid_at = now();
                    }
                    if ($order->status === OrderStatus::AwaitingDeposit) {
                        $order->status = OrderStatus::Confirmed;
                        $order->ready_by = now()->addDays((int) config('studio.delivery.lead_time_days', 7));
                        $this->transitions->recordOrderEvent($order, ProductionEventType::DepositReceived);
                        $this->transitions->initializeAfterDeposit($order);
                        $this->proofs->createInitial($order);
                        $sendDepositConfirmation = $order->id;
                    }
                } elseif ($payment->kind === PaymentKind::Balance) {
                    $order->payment_status = PaymentStatus::Paid;
                    $order->balance_paid_at = now();
                    $this->transitions->recordOrderEvent(
                        $order,
                        ProductionEventType::BalanceReceived,
                        item: $order->items()->first(),
                    );
                    $sendBalanceConfirmation = true;
                    $refreshDelivery = true;
                }
            } elseif ($event->status === PaymentRecordStatus::Failed) {
                $this->transitions->recordOrderEvent(
                    $order,
                    ProductionEventType::PaymentFailed,
                    'Payment attempt failed.',
                );
            }

            $payment->save();
            $order->save();
            if ($refreshDelivery) {
                $this->transitions->refreshDeliveryReadiness($order);
            }

            return [
                'deposit' => $sendDepositConfirmation,
                'balance' => $sendBalanceConfirmation ? $order->id : null,
            ];
        });

        if ($mailOrder !== null) {
            DB::afterCommit(static function () use ($mailOrder): void {
                $depositOrderId = $mailOrder['deposit'];
                if ($depositOrderId !== null) {
                    $order = Order::query()->with(['items', 'user'])->findOrFail($depositOrderId);
                    Mail::to($order->user->email)
                        ->queue((new OrderConfirmationMail($order))->afterCommit());
                }
                $balanceOrderId = $mailOrder['balance'];
                if ($balanceOrderId !== null) {
                    $order = Order::query()->with('user')->findOrFail($balanceOrderId);
                    Mail::to($order->user->email)
                        ->queue((new OrderNoticeMail($order, 'balance_received'))->afterCommit());
                }
            });
        }

        return true;
    }
}
