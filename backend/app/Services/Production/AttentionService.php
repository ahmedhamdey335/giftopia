<?php

namespace App\Services\Production;

use App\Enums\OrderStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\ProductionStatus;
use App\Enums\ProofStatus;
use App\Models\OrderItem;
use App\Services\Studio\ProductionFeasibilityChecker;

final class AttentionService
{
    public function __construct(
        private readonly TransitionService $transitions,
        private readonly ProductionFeasibilityChecker $feasibility,
    ) {}

    /**
     * @return list<array{code: string, note: ?string}>
     */
    public function reasons(OrderItem $item): array
    {
        $reasons = [];
        $missingMaterials = $this->transitions->unavailableMaterials($item);
        if ($missingMaterials !== []) {
            $reasons[] = [
                'code' => 'material_unavailable',
                'note' => implode(', ', array_column($missingMaterials, 'name')),
            ];
        }

        if ($item->production_status === ProductionStatus::AwaitingProof
            && ($item->proof_status === ProofStatus::None
                || ($item->proof_status === ProofStatus::Pending
                    && $item->order->deposit_paid_at?->lessThanOrEqualTo(now()->subHours(24))))) {
            $reasons[] = ['code' => 'proof_missing_or_pending_too_long', 'note' => null];
        }

        if ($item->order->ready_by->isPast()
            && ! in_array($item->order->status, [OrderStatus::Delivered, OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
            $reasons[] = ['code' => 'overdue', 'note' => null];
        }

        if ($this->feasibility->isAtRisk($item)) {
            $reasons[] = ['code' => 'at_risk', 'note' => null];
        }

        if ($item->order->payments()->where('status', PaymentRecordStatus::Failed->value)
            ->whereIn('kind', ['deposit', 'balance'])->exists()) {
            $reasons[] = ['code' => 'payment_problem', 'note' => null];
        }

        if ($item->order->refundRequests()->whereIn('status', ['requested', 'failed'])->exists()) {
            $reasons[] = ['code' => 'refund_request', 'note' => null];
        }

        if ($item->needs_attention) {
            $reasons[] = ['code' => 'manual', 'note' => $item->attention_note];
        }

        return $reasons;
    }
}
