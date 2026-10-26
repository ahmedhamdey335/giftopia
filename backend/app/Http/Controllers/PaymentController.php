<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Exceptions\CheckoutException;
use App\Exceptions\InvalidPaymentWebhook;
use App\Exceptions\InvalidWebhookSignature;
use App\Http\Requests\PaymentAttemptRequest;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function store(PaymentAttemptRequest $request, Order $order, PaymentService $payments): JsonResponse
    {
        $this->authorize('view', $order);

        try {
            $payment = $payments->createAttempt(
                $order,
                PaymentKind::from($request->validated('kind')),
                PaymentMethod::from($request->validated('method')),
            );
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success([
            'redirect_url' => $payment->redirect_url,
            'amount' => $payment->amount,
            'kind' => $payment->kind->value,
            'method' => $payment->method->value,
            'status' => $payment->status->value,
        ], 'Payment attempt created.');
    }

    public function webhook(Request $request, string $provider, PaymentService $payments): JsonResponse
    {
        try {
            $payments->handleWebhook($provider, $request);
        } catch (InvalidWebhookSignature $exception) {
            return ApiResponse::error('Invalid webhook signature.', status: 401);
        } catch (InvalidPaymentWebhook $exception) {
            return ApiResponse::error($exception->getMessage(), status: 422);
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success(['received' => true], 'Webhook received.');
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        try {
            DB::transaction(function () use ($order): void {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                if ($lockedOrder->status !== OrderStatus::AwaitingDeposit) {
                    throw new CheckoutException('Only orders awaiting a deposit can be cancelled.', 422);
                }

                $lockedOrder->status = OrderStatus::Cancelled;
                $lockedOrder->cancelled_at = now();
                $lockedOrder->save();
                $lockedOrder->payments()
                    ->where('kind', PaymentKind::Deposit->value)
                    ->where('status', PaymentRecordStatus::Pending->value)
                    ->update(['status' => PaymentRecordStatus::Cancelled->value]);
            });
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success(['status' => OrderStatus::Cancelled->value], 'Order cancelled.');
    }
}
