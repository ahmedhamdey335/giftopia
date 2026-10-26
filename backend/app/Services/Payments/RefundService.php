<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionEventType;
use App\Enums\RefundRequestStatus;
use App\Exceptions\CheckoutException;
use App\Mail\RefundNotificationMail;
use App\Models\Asset;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\Production\TransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

final class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly TransitionService $transitions,
    ) {}

    /**
     * @param  list<string>  $evidenceIds
     */
    public function request(Order $order, User $customer, string $reason, array $evidenceIds = []): RefundRequest
    {
        return DB::transaction(function () use ($order, $customer, $reason, $evidenceIds): RefundRequest {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($lockedOrder->user_id !== $customer->id) {
                throw new CheckoutException('Order not found.', 404);
            }
            if (! in_array($lockedOrder->status, [
                OrderStatus::AwaitingBalance,
                OrderStatus::ReadyForDelivery,
                OrderStatus::OutForDelivery,
                OrderStatus::Delivered,
            ], true)) {
                throw new CheckoutException('A refund cannot be requested for this order status.', 422);
            }
            if ($lockedOrder->status === OrderStatus::Delivered
                && ($lockedOrder->delivered_at === null
                    || $lockedOrder->delivered_at->addDays((int) config('studio.delivery.claim_window_days', 3))->isPast())) {
                throw new CheckoutException('The refund claim window has expired.', 422);
            }
            if ($lockedOrder->refundRequests()->whereIn('status', ['requested', 'approved', 'failed'])->exists()) {
                throw new CheckoutException('An open refund request already exists for this order.', 409);
            }
            $assets = Asset::query()->whereIn('id', $evidenceIds)
                ->where('kind', 'refund_evidence')
                ->where('owner_user_id', $customer->id)
                ->whereNull('refund_request_id')
                ->lockForUpdate()->get();
            if ($assets->count() !== count(array_unique($evidenceIds))) {
                throw new CheckoutException('Refund evidence must be uploaded by the order owner.', 422, [
                    'evidence_asset_ids' => ['One or more evidence assets are invalid or already attached.'],
                ]);
            }

            $refund = $lockedOrder->refundRequests()->create([
                'user_id' => $customer->id,
                'reason' => $reason,
                'status' => RefundRequestStatus::Requested,
                'requested_amount' => $lockedOrder->amount_paid,
            ]);
            foreach ($assets as $asset) {
                $asset->update(['refund_request_id' => $refund->id, 'attached_at' => now()]);
            }
            $this->transitions->recordOrderEvent(
                $lockedOrder,
                ProductionEventType::RefundRequested,
                'Refund request #'.$refund->id,
            );
            $refund->load('order');
            $staffEmail = (string) config('studio.staff_email', '');
            if ($staffEmail !== '') {
                Mail::to($staffEmail)->queue((new RefundNotificationMail($refund, true))->afterCommit());
            } else {
                Log::warning('Refund request created, but staff notification email is not configured.', [
                    'order_id' => $lockedOrder->id,
                    'refund_request_id' => $refund->id,
                ]);
            }

            return $refund;
        });
    }

    public function decide(
        RefundRequest $refund,
        User $actor,
        bool $approve,
        ?int $amount = null,
        ?string $note = null,
    ): RefundRequest {
        if (! $approve) {
            if ($refund->status !== RefundRequestStatus::Requested) {
                throw new CheckoutException('Only a requested refund can be rejected.', 422);
            }
            $refund->update([
                'status' => RefundRequestStatus::Rejected,
                'decision_note' => $note,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);
            $this->recordDecision($refund, $actor);
            $this->notifyCustomer($refund);

            return $refund->fresh();
        }

        return DB::transaction(function () use ($refund, $actor, $amount, $note): RefundRequest {
            $locked = RefundRequest::query()->with('order')->lockForUpdate()->findOrFail($refund->id);
            if (! in_array($locked->status, [RefundRequestStatus::Requested, RefundRequestStatus::Failed], true)) {
                throw new CheckoutException('This refund request has already been decided.', 422);
            }
            $previouslyRefundedForRequest = (int) $locked->order->payments()
                ->where('kind', PaymentKind::Refund->value)
                ->where('status', PaymentRecordStatus::Succeeded->value)
                ->where('meta->refund_request_id', $locked->id)->sum('amount');
            $approvedAmount = $amount ?? $locked->approved_amount ?? $locked->order->amount_paid;
            if ($approvedAmount < 1 || $approvedAmount > $locked->order->amount_paid + $previouslyRefundedForRequest) {
                throw new CheckoutException('Approved refund amount cannot exceed the amount paid.', 422, [
                    'approved_amount' => ['Choose an amount no greater than the amount paid.'],
                ]);
            }
            $locked->approved_amount = $approvedAmount;
            $locked->decision_note = $note;
            $locked->decided_by = $actor->id;
            $locked->decided_at = now();
            $locked->status = RefundRequestStatus::Approved;
            $locked->save();

            $remaining = max(0, $approvedAmount - $previouslyRefundedForRequest);
            $failed = false;
            $failureReason = null;
            $payments = $locked->order->payments()
                ->whereIn('kind', [PaymentKind::Deposit->value, PaymentKind::Balance->value])
                ->where('status', PaymentRecordStatus::Succeeded->value)
                ->oldest()->get();
            foreach ($payments as $payment) {
                if ($remaining < 1) {
                    break;
                }
                $refundedFromPayment = (int) $locked->order->payments()
                    ->where('kind', PaymentKind::Refund->value)
                    ->where('status', PaymentRecordStatus::Succeeded->value)
                    ->where('meta->original_payment_id', $payment->id)
                    ->sum('amount');
                $available = max(0, $payment->amount - $refundedFromPayment);
                $refundAmount = min($remaining, $available);
                if ($refundAmount < 1) {
                    continue;
                }

                try {
                    $result = $this->gateway->refund($payment, $refundAmount);
                } catch (Throwable $exception) {
                    $result = new RefundResult(false, null, $exception->getMessage());
                }
                $locked->order->payments()->create([
                    'kind' => PaymentKind::Refund,
                    'method' => $payment->method,
                    'provider' => $payment->provider,
                    'provider_reference' => $result->reference ?? (string) Str::uuid(),
                    'amount' => $refundAmount,
                    'currency' => $payment->currency,
                    'status' => $result->succeeded ? PaymentRecordStatus::Succeeded : PaymentRecordStatus::Failed,
                    'failure_reason' => $result->failureReason,
                    'paid_at' => $result->succeeded ? now() : null,
                    'meta' => ['original_payment_id' => $payment->id, 'refund_request_id' => $locked->id],
                ]);
                if (! $result->succeeded) {
                    $failed = true;
                    $failureReason = $result->failureReason ?? 'The payment provider could not complete the refund.';
                    break;
                }
                $remaining -= $refundAmount;
            }

            $successfulRefunds = (int) $locked->order->payments()
                ->where('kind', PaymentKind::Refund->value)
                ->where('status', PaymentRecordStatus::Succeeded->value)->sum('amount');
            $netPaid = max(0, $locked->order->amount_paid - $successfulRefunds);
            $locked->order->amount_paid = $netPaid;
            if ($successfulRefunds > 0) {
                $locked->order->payment_status = $netPaid === 0
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded;
            }
            if ($netPaid === 0) {
                $locked->order->status = OrderStatus::Refunded;
            }
            $locked->order->save();
            $locked->status = $failed || $remaining > 0
                ? RefundRequestStatus::Failed
                : RefundRequestStatus::Refunded;
            if ($failureReason !== null) {
                $locked->decision_note = $failureReason;
            }
            $locked->save();
            $this->transitions->recordOrderEvent(
                $locked->order,
                ProductionEventType::RefundDecided,
                $locked->status->value,
                $actor,
            );
            $locked->load('order');
            Mail::to($locked->order->user->email)->queue((new RefundNotificationMail($locked))->afterCommit());

            return $locked;
        });
    }

    private function recordDecision(RefundRequest $refund, User $actor): void
    {
        $this->transitions->recordOrderEvent(
            $refund->order,
            ProductionEventType::RefundDecided,
            $refund->status->value,
            $actor,
        );
    }

    private function notifyCustomer(RefundRequest $refund): void
    {
        $refund->loadMissing('order.user');
        Mail::to($refund->order->user->email)->queue((new RefundNotificationMail($refund))->afterCommit());
    }
}
