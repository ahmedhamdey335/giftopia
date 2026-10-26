<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Exceptions\CheckoutException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualAttentionRequest;
use App\Http\Requests\Admin\ProductionChecklistRequest;
use App\Http\Requests\Admin\ProductionSheetIndexRequest;
use App\Http\Requests\Admin\ProductionTransitionRequest;
use App\Http\Requests\Studio\AssetUploadRequest;
use App\Models\OrderItem;
use App\Services\Assets\AssetUploadService;
use App\Services\Production\AttentionService;
use App\Services\Production\ProductionSheetService;
use App\Services\Production\TransitionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ProductionSheetController extends Controller
{
    public function index(
        ProductionSheetIndexRequest $request,
        AttentionService $attention,
        ProductionSheetService $sheets,
    ): JsonResponse {
        $this->authorize('viewAny', OrderItem::class);
        $filters = $request->validated();
        $view = $filters['view'] ?? 'today';
        $query = OrderItem::query()->with('order');

        if (isset($filters['status'])) {
            $query->where('production_status', $filters['status']);
        } elseif ($view === 'today') {
            $query->whereIn('production_status', [
                ProductionStatus::ReadyToStart->value,
                ProductionStatus::InProduction->value,
                ProductionStatus::QualityCheck->value,
            ]);
        } elseif ($view === 'waiting') {
            $query->where('production_status', ProductionStatus::AwaitingProof->value);
        } elseif ($view === 'shipping') {
            $query->where('production_status', ProductionStatus::Packed->value)
                ->whereHas('order', fn ($orders) => $orders->whereIn('status', [
                    OrderStatus::AwaitingBalance->value,
                    OrderStatus::ReadyForDelivery->value,
                    OrderStatus::OutForDelivery->value,
                ]));
        }

        if (isset($filters['due_from'])) {
            $query->whereHas('order', fn ($orders) => $orders->whereDate('ready_by', '>=', $filters['due_from']));
        }
        if (isset($filters['due_to'])) {
            $query->whereHas('order', fn ($orders) => $orders->whereDate('ready_by', '<=', $filters['due_to']));
        }
        if (isset($filters['q'])) {
            $query->whereHas('order', function ($orders) use ($filters): void {
                $orders->where('order_number', 'like', '%'.$filters['q'].'%')
                    ->orWhere('shipping_name', 'like', '%'.$filters['q'].'%');
            });
        }

        $items = $query->get()->sortBy(fn (OrderItem $item): string => $item->order->ready_by->toIso8601String())->values();
        if ($view === 'needs_attention') {
            $items = $items->filter(fn (OrderItem $item): bool => $attention->reasons($item) !== [])->values();
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $pageItems = $items->forPage($page, $perPage)->values();

        return ApiResponse::success([
            'items' => $pageItems->map(fn (OrderItem $item): array => [
                'item_id' => $item->id,
                'order_number' => $item->order->order_number,
                'production_status' => $item->production_status->value,
                'due_date' => $item->order->ready_by->toIso8601String(),
                'attention_reasons' => $attention->reasons($item),
                'card' => $sheets->card($item),
            ])->all(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $items->count(),
                'last_page' => max(1, (int) ceil($items->count() / $perPage)),
            ],
        ]);
    }

    public function show(OrderItem $item, ProductionSheetService $sheets): JsonResponse
    {
        $this->authorize('view', $item);

        return ApiResponse::success($sheets->card($item));
    }

    public function checklist(
        ProductionChecklistRequest $request,
        OrderItem $item,
        ProductionSheetService $sheets,
    ): JsonResponse {
        $this->authorize('update', $item);

        try {
            $updated = $sheets->updateChecklist($item, $request->validated('checks'));
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success($sheets->card($updated), 'Checklist updated.');
    }

    public function transition(
        ProductionTransitionRequest $request,
        OrderItem $item,
        TransitionService $transitions,
        ProductionSheetService $sheets,
    ): JsonResponse {
        $this->authorize('update', $item);

        try {
            $updated = $transitions->transition($item, $request->validated('to'), $request->user(), $request->validated());
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success($sheets->card($updated), 'Production transition recorded.');
    }

    public function setAttention(ManualAttentionRequest $request, OrderItem $item): JsonResponse
    {
        $this->authorize('update', $item);
        $item->needs_attention = true;
        $item->attention_note = trim($request->validated('note'));
        $item->save();

        return ApiResponse::success(['needs_attention' => true, 'attention_note' => $item->attention_note]);
    }

    public function clearAttention(OrderItem $item): JsonResponse
    {
        $this->authorize('update', $item);
        $item->needs_attention = false;
        $item->attention_note = null;
        $item->save();

        return ApiResponse::success(['needs_attention' => false]);
    }

    public function qcPhotos(
        AssetUploadRequest $request,
        OrderItem $item,
        AssetUploadService $uploads,
    ): JsonResponse {
        $this->authorize('update', $item);
        $assets = $uploads->uploadQcPhotos($request->file('files'), $request->user(), $item);

        return ApiResponse::success([
            'items' => collect($assets)->map(fn ($asset): array => [
                'id' => $asset->id,
                'file_url' => URL::temporarySignedRoute(
                    'assets.file',
                    now()->addMinutes(15),
                    ['asset' => $asset->id],
                ),
            ])->all(),
        ], 'QC photos uploaded.', 201);
    }

    public function batches(): JsonResponse
    {
        $this->authorize('viewAny', OrderItem::class);
        $groups = OrderItem::query()
            ->with('order')
            ->where('production_status', ProductionStatus::ReadyToStart->value)
            ->get()
            ->groupBy(function (OrderItem $item): string {
                $snapshot = $item->customization;

                return ($snapshot['color']['base']['id'] ?? 'none').':'.($snapshot['base_item']['mold']['id'] ?? 'none');
            })
            ->map(function ($items): array {
                $first = $items->first();
                $snapshot = $first->customization;

                return [
                    'base_color' => $snapshot['color']['base'] ?? null,
                    'mold' => $snapshot['base_item']['mold'] ?? null,
                    'items' => $items->map(fn (OrderItem $item): array => [
                        'item_id' => $item->id,
                        'order_number' => $item->order->order_number,
                    ])->values()->all(),
                ];
            })
            ->values();

        return ApiResponse::success(['batches' => $groups]);
    }

    public function print(OrderItem $item, ProductionSheetService $sheets): Response
    {
        $this->authorize('view', $item);

        return response()->view('admin.production-sheet-print', ['card' => $sheets->card($item)]);
    }
}
