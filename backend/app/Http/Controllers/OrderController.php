<?php

namespace App\Http\Controllers;

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

        return ApiResponse::success(
            (new CustomerOrderResource($order))->resolve($request),
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
}
