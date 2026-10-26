<?php

namespace App\Http\Controllers;

use App\Enums\ProductionEventType;
use App\Exceptions\CheckoutException;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        try {
            $result = $checkout->checkout($request->user(), $request->validated());
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        if ($result->isPriceChanged()) {
            $quote = $result->updatedQuote;

            return ApiResponse::error('The price has changed. Review and confirm the new quote.', [
                'quote' => [
                    'quote_id' => $quote->id,
                    'price_breakdown' => $quote->price_breakdown,
                    'total' => $quote->total_price,
                    'ready_at' => $quote->ready_at->toIso8601String(),
                    'expires_at' => $quote->expires_at->toIso8601String(),
                ],
            ], 409);
        }

        $order = $result->order->load('items');
        $data = (new CustomerOrderResource($order))->resolve($request);
        $data['payment'] = $result->payment === null ? null : [
            'redirect_url' => $result->payment->redirect_url,
            'amount' => $result->payment->amount,
            'expires_at' => $order->deposit_expires_at?->toIso8601String(),
        ];

        return ApiResponse::success(
            $data,
            $result->status === 201 ? 'Order placed.' : 'Existing order returned.',
            $result->status,
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);
        $orders = $request->user()->orders()->with('items')->latest()->paginate(20);

        return ApiResponse::success([
            'items' => $orders->getCollection()
                ->map(fn (Order $order): array => (new CustomerOrderResource($order))->resolve($request))
                ->all(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return ApiResponse::success(
            (new CustomerOrderResource($order->load('items')))->resolve($request),
        );
    }

    public function timeline(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        $safeTypes = [
            ProductionEventType::DepositReceived,
            ProductionEventType::PaymentFailed,
            ProductionEventType::ProofRequested,
            ProductionEventType::ProofApproved,
            ProductionEventType::ProofChangesRequested,
            ProductionEventType::BalanceRequested,
            ProductionEventType::BalanceReceived,
            ProductionEventType::OutForDelivery,
            ProductionEventType::Delivered,
            ProductionEventType::RefundRequested,
            ProductionEventType::RefundDecided,
        ];
        $labels = [
            'deposit_received' => 'Deposit paid',
            'payment_failed' => 'Payment attempt failed',
            'proof_requested' => 'Personalization proof requested',
            'proof_approved' => 'Proof approved',
            'proof_changes_requested' => 'Proof changes requested',
            'balance_requested' => 'Balance payment requested',
            'balance_received' => 'Balance paid',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'refund_requested' => 'Refund requested',
            'refund_decided' => 'Refund decision updated',
        ];
        $events = $order->productionEvents()
            ->whereIn('event_type', array_map(static fn (ProductionEventType $type): string => $type->value, $safeTypes))
            ->oldest()
            ->get()
            ->map(fn ($event): array => [
                'type' => $event->event_type->value,
                'label' => $labels[$event->event_type->value],
                'created_at' => $event->created_at->toIso8601String(),
            ])
            ->values();

        return ApiResponse::success(['events' => $events]);
    }
}
