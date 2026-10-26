<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\Admin\RefundDecisionRequest;
use App\Http\Requests\Admin\RefundRejectionRequest;
use App\Http\Requests\RefundEvidenceUploadRequest;
use App\Http\Requests\RefundRequestCreateRequest;
use App\Models\Order;
use App\Models\RefundRequest as RefundModel;
use App\Services\Assets\AssetUploadService;
use App\Services\Payments\RefundService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class RefundController extends Controller
{
    public function uploadEvidence(
        RefundEvidenceUploadRequest $request,
        AssetUploadService $uploads,
    ): JsonResponse {
        $order = Order::query()->findOrFail($request->validated('order_id'));
        $this->authorize('view', $order);
        $assets = $uploads->uploadRefundEvidence($request->file('files'), $request->user());

        return ApiResponse::success([
            'items' => collect($assets)->map(fn ($asset): array => [
                'id' => $asset->id,
                'url' => URL::temporarySignedRoute('assets.file', now()->addMinutes(15), ['asset' => $asset->id]),
            ])->all(),
        ], 'Refund evidence uploaded.', 201);
    }

    public function store(
        RefundRequestCreateRequest $request,
        Order $order,
        RefundService $refunds,
    ): JsonResponse {
        $this->authorize('view', $order);
        try {
            $refund = $refunds->request(
                $order,
                $request->user(),
                $request->validated('reason'),
                $request->validated('evidence_asset_ids', []),
            );
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success($this->customerView($refund), 'Refund request submitted.', 201);
    }

    public function index(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        $items = $order->refundRequests()->with('evidence')->latest()->get()
            ->map(fn (RefundModel $refund): array => $this->customerView($refund));

        return ApiResponse::success(['items' => $items]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['sometimes', 'string', 'in:requested,approved,rejected,refunded,failed']]);
        $query = RefundModel::query()->with(['order.user', 'evidence'])->latest();
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $page = $query->paginate(20);

        return ApiResponse::success([
            'items' => $page->getCollection()->map(fn (RefundModel $refund): array => $this->staffView($refund))->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function approve(
        RefundDecisionRequest $request,
        RefundModel $refund,
        RefundService $refunds,
    ): JsonResponse {
        $this->authorize('update', $refund->order);
        try {
            $result = $refunds->decide(
                $refund,
                $request->user(),
                true,
                $request->validated('approved_amount'),
                $request->validated('note'),
            );
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success($this->staffView($result->load(['order.user', 'evidence'])), 'Refund decision recorded.');
    }

    public function reject(
        RefundRejectionRequest $request,
        RefundModel $refund,
        RefundService $refunds,
    ): JsonResponse {
        $this->authorize('update', $refund->order);
        try {
            $result = $refunds->decide($refund, $request->user(), false, note: $request->validated('note'));
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success($this->staffView($result->load(['order.user', 'evidence'])), 'Refund rejected.');
    }

    private function customerView(RefundModel $refund): array
    {
        return [
            'id' => $refund->id,
            'status' => $refund->status->value,
            'reason' => $refund->reason,
            'requested_amount' => $refund->requested_amount,
            'approved_amount' => $refund->approved_amount,
            'decision_note' => $refund->decision_note,
            'evidence' => $refund->evidence->map(fn ($asset): array => [
                'id' => $asset->id,
                'url' => URL::temporarySignedRoute('assets.file', now()->addMinutes(15), ['asset' => $asset->id]),
            ])->all(),
            'created_at' => $refund->created_at->toIso8601String(),
        ];
    }

    private function staffView(RefundModel $refund): array
    {
        $orderItems = $refund->order->items()->with('proofs')->get();

        return [
            ...$this->customerView($refund),
            'order_id' => $refund->order_id,
            'order_number' => $refund->order->order_number,
            'customer_name' => $refund->order->shipping_name,
            'approved_proofs' => $orderItems->flatMap(fn ($item) => $item->proofs
                ->where('status', 'approved')->map(fn ($proof): array => [
                    'item_id' => $item->id,
                    'version' => $proof->version,
                    'text' => $proof->text,
                ]))->values(),
            'qc_photos' => $orderItems->flatMap(fn ($item) => $item->assets->where('kind', 'qc')
                ->map(fn ($asset): array => [
                    'item_id' => $item->id,
                    'url' => URL::temporarySignedRoute('assets.file', now()->addMinutes(15), ['asset' => $asset->id]),
                ]))->values(),
        ];
    }
}
