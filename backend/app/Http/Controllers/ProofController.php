<?php

namespace App\Http\Controllers;

use App\Exceptions\CheckoutException;
use App\Http\Requests\Admin\ProofRevisionRequest;
use App\Http\Requests\ProofChangesRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Production\ProofService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ProofController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        $proofs = $order->items()->with('proofs')->get()->flatMap(fn (OrderItem $item) => $item->proofs)
            ->sortBy('version')->values()
            ->map(fn ($proof): array => [
                'item_id' => $proof->order_item_id,
                'version' => $proof->version,
                'text' => $proof->text,
                'font_name' => $proof->font_name,
                'text_color_name' => $proof->text_color_name,
                'note' => $proof->note,
                'status' => $proof->status,
                'requested_at' => $proof->requested_at->toIso8601String(),
                'auto_approve_at' => $proof->auto_approve_at->toIso8601String(),
                'preview_url' => $proof->preview_asset_id === null
                    ? null
                    : URL::temporarySignedRoute('assets.file', now()->addMinutes(15), ['asset' => $proof->preview_asset_id]),
            ]);

        return ApiResponse::success(['items' => $proofs]);
    }

    public function approve(Order $order, Request $request, ProofService $proofs): JsonResponse
    {
        $this->authorize('view', $order);
        $items = [];
        foreach ($order->items()->where('has_text', true)->get() as $item) {
            $items[] = $proofs->approve($item, $request->user())->id;
        }

        return ApiResponse::success(['approved_proof_ids' => $items], 'Proof approved.');
    }

    public function requestChanges(
        ProofChangesRequest $request,
        Order $order,
        ProofService $proofs,
    ): JsonResponse {
        $this->authorize('view', $order);
        $items = [];
        foreach ($order->items()->where('has_text', true)->get() as $item) {
            try {
                $items[] = $proofs->requestChanges($item, $request->validated('note'), $request->user())->id;
            } catch (CheckoutException $exception) {
                return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
            }
        }

        return ApiResponse::success(['proof_ids' => $items], 'Proof changes requested.');
    }

    public function revise(
        ProofRevisionRequest $request,
        OrderItem $item,
        ProofService $proofs,
    ): JsonResponse {
        $this->authorize('update', $item);
        try {
            $proof = $proofs->revise(
                $item,
                $request->validated('text'),
                $request->validated('note'),
                $request->user(),
            );
        } catch (CheckoutException $exception) {
            return ApiResponse::error($exception->getMessage(), $exception->errors, $exception->status);
        }

        return ApiResponse::success([
            'id' => $proof->id,
            'item_id' => $proof->order_item_id,
            'version' => $proof->version,
            'text' => $proof->text,
            'status' => $proof->status,
            'auto_approve_at' => $proof->auto_approve_at->toIso8601String(),
        ], 'Revised proof requested.', 201);
    }
}
