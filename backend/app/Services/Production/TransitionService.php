<?php

namespace App\Services\Production;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionEventType;
use App\Enums\ProductionStatus;
use App\Enums\ProofStatus;
use App\Enums\UserRole;
use App\Exceptions\CheckoutException;
use App\Mail\BalanceRequestedMail;
use App\Mail\OrderNoticeMail;
use App\Models\Asset;
use App\Models\ColorSwatch;
use App\Models\Insert;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class TransitionService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'placed' => ['awaiting_proof', 'ready_to_start'],
        'awaiting_proof' => ['ready_to_start'],
        'ready_to_start' => ['in_production'],
        'in_production' => ['curing'],
        'curing' => ['qc'],
        'qc' => ['packed'],
        'packed' => [],
    ];

    /**
     * @param  array<string, mixed>  $options
     */
    public function transition(OrderItem $item, string $to, User $actor, array $options = []): OrderItem
    {
        if (OrderStatus::tryFrom($to) !== null) {
            return $this->transitionOrder($item, $to, $actor, $options);
        }

        $result = DB::transaction(function () use ($item, $to, $actor, $options): array {
            $locked = OrderItem::query()->with('order')->lockForUpdate()->findOrFail($item->id);
            $from = $locked->production_status->value;
            $allowed = $this->allowedNextTransitions($locked);
            if (! in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true)) {
                throw new CheckoutException('Illegal production transition.', 422, [
                    'allowed_next_states' => $allowed,
                ]);
            }
            if (! in_array($actor->role, [UserRole::Admin->value, UserRole::Producer->value], true)) {
                throw new CheckoutException('Only producers and admins can update production.', 403);
            }
            if ($from === ProductionStatus::Placed->value) {
                throw new CheckoutException('Initial production transitions are system-managed after deposit confirmation.', 403);
            }
            if ($from === ProductionStatus::AwaitingProof->value
                && $locked->proof_status !== ProofStatus::Approved) {
                throw new CheckoutException('The customer must approve the proof before production starts.', 422);
            }
            if ($from === ProductionStatus::ReadyToStart->value && $to === ProductionStatus::InProduction->value) {
                $missing = $this->unavailableMaterials($locked);
                if ($missing !== []) {
                    $locked->needs_attention = true;
                    $locked->attention_note = 'Unavailable materials: '.implode(', ', array_column($missing, 'name'));
                    $locked->save();

                    return ['missing_materials' => $missing];
                }
            }
            if ($from === ProductionStatus::Curing->value && $to === ProductionStatus::QualityCheck->value) {
                $override = (bool) ($options['override'] ?? false);
                if ($locked->cure_started_at === null) {
                    throw new CheckoutException('Curing must be started before quality check.', 422);
                }
                $cureCompleteAt = $locked->cure_started_at
                    ->addHours((int) ($locked->customization['cure_hours'] ?? 0));
                if (now()->lessThan($cureCompleteAt)
                    && ! ($override && $actor->role === UserRole::Admin->value && filled($options['note'] ?? null))) {
                    throw new CheckoutException('The item has not completed its cure time.', 422, [
                        'cure_complete_at' => [$cureCompleteAt->toIso8601String()],
                    ]);
                }
            }

            $qcAssets = collect();
            if ($from === ProductionStatus::QualityCheck->value && $to === ProductionStatus::Packed->value) {
                $ids = array_values(array_unique((array) ($options['asset_ids'] ?? [])));
                $qcAssets = Asset::query()
                    ->whereIn('id', $ids)
                    ->where('kind', 'qc')
                    ->where('order_item_id', $locked->id)
                    ->lockForUpdate()
                    ->get();
                if ($ids === [] || $qcAssets->count() !== count($ids)) {
                    throw new CheckoutException('Attach a QC photo before packing the item.', 422, [
                        'asset_ids' => ['At least one QC photo asset belonging to this item is required.'],
                    ]);
                }
            }

            $locked->production_status = ProductionStatus::from($to);
            if ($to === ProductionStatus::InProduction->value) {
                $locked->started_at = now();
            } elseif ($to === ProductionStatus::Curing->value) {
                $locked->cure_started_at = now();
            } elseif ($to === ProductionStatus::QualityCheck->value) {
                $locked->qc_at = now();
            } elseif ($to === ProductionStatus::Packed->value) {
                $locked->packed_at = now();
                $locked->actual_labor_minutes = $options['actual_labor_minutes'] ?? null;
            }
            $locked->save();
            $this->record($locked, $from, $to, $actor, $options['note'] ?? null);

            if ($to === ProductionStatus::Packed->value) {
                $this->refreshDeliveryReadiness($locked->order, $actor);
            }

            return ['item' => $locked->fresh(['order']), 'qc_assets' => $qcAssets];
        });

        if (isset($result['missing_materials'])) {
            throw new CheckoutException('Required materials are unavailable.', 422, [
                'missing_materials' => $result['missing_materials'],
            ]);
        }

        $packedItem = $result['item'];
        if ($to === ProductionStatus::Packed->value
            && $packedItem->order->status === OrderStatus::AwaitingBalance
            && $result['qc_assets']->isNotEmpty()) {
            $orderId = $packedItem->order_id;
            $photo = $result['qc_assets']->first();
            $photoUrl = URL::temporarySignedRoute(
                'assets.file',
                now()->addMinutes(15),
                ['asset' => $photo->id],
            );
            DB::afterCommit(static function () use ($orderId, $photoUrl): void {
                $order = Order::query()->with('user')->findOrFail($orderId);
                Mail::to($order->user->email)->queue((new BalanceRequestedMail($order, $photoUrl))->afterCommit());
            });
        }

        return $packedItem;
    }

    public function initializeAfterDeposit(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            foreach ($order->items()->lockForUpdate()->get() as $item) {
                if ($item->production_status !== ProductionStatus::Placed) {
                    continue;
                }

                $to = $item->has_text
                    ? ProductionStatus::AwaitingProof
                    : ProductionStatus::ReadyToStart;
                $from = ProductionStatus::Placed->value;
                $item->production_status = $to;
                if ($item->has_text) {
                    $item->proof_status = ProofStatus::Pending;
                }
                $item->save();
                $this->record($item, $from, $to->value, null, 'Deposit confirmed.');
            }
        });
    }

    public function recordEvent(
        OrderItem $item,
        ProductionEventType $eventType,
        string $from,
        string $to,
        ?string $note,
        ?User $actor = null,
    ): ProductionEvent {
        return $this->record($item, $from, $to, $actor, $note, $eventType);
    }

    public function recordOrderEvent(
        Order $order,
        ProductionEventType $eventType,
        ?string $note = null,
        ?User $actor = null,
        ?OrderItem $item = null,
    ): ProductionEvent {
        return ProductionEvent::query()->create([
            'order_id' => $order->id,
            'order_item_id' => $item?->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'note' => $note,
        ]);
    }

    public function refreshDeliveryReadiness(Order $order, ?User $actor = null): void
    {
        $order->refresh();
        if (! $order->items()->exists()
            || $order->items()->where('production_status', '!=', ProductionStatus::Packed->value)->exists()) {
            return;
        }

        $from = $order->status->value;
        $to = $order->payment_status === PaymentStatus::Paid
            ? OrderStatus::ReadyForDelivery
            : OrderStatus::AwaitingBalance;
        if ($from === $to->value) {
            return;
        }

        $order->status = $to;
        if ($to === OrderStatus::ReadyForDelivery) {
            $order->ready_for_delivery_at = now();
        }
        $order->save();
        $eventType = $to === OrderStatus::AwaitingBalance
            ? ProductionEventType::BalanceRequested
            : ProductionEventType::StatusChanged;
        $this->record($order->items()->firstOrFail(), $from, $to->value, $actor, null, $eventType);
    }

    /**
     * @return list<string>
     */
    public function allowedNextTransitions(OrderItem $item): array
    {
        $status = $item->production_status->value;
        $allowed = self::ALLOWED_TRANSITIONS[$status] ?? [];
        if ($status === ProductionStatus::AwaitingProof->value
            && $item->proof_status !== ProofStatus::Approved) {
            return [];
        }

        if ($status === ProductionStatus::Packed->value) {
            return match ($item->order->status->value) {
                'ready_for_delivery' => ['out_for_delivery'],
                'out_for_delivery' => ['delivered'],
                default => [],
            };
        }

        return $allowed;
    }

    /**
     * @return list<array{key: string, name: string, available: bool}>
     */
    public function unavailableMaterials(OrderItem $item): array
    {
        return array_values(array_filter(
            $this->materials($item),
            static fn (array $material): bool => ! $material['available'],
        ));
    }

    /**
     * @return list<array{key: string, name: string, available: bool}>
     */
    public function materials(OrderItem $item): array
    {
        $configuration = $item->customization;
        $materials = [];
        $baseColorId = $configuration['color']['base']['id'] ?? null;
        $accentColorId = $configuration['color']['accent']['id'] ?? null;
        foreach ([
            ['id' => $baseColorId, 'name' => $configuration['color']['base']['name'] ?? 'Base pigment'],
            ['id' => $accentColorId, 'name' => $configuration['color']['accent']['name'] ?? 'Accent pigment'],
        ] as $color) {
            if ($color['id'] === null) {
                continue;
            }
            $available = ColorSwatch::query()
                ->whereKey($color['id'])
                ->where('is_available', true)
                ->exists();
            $materials[] = [
                'key' => 'swatch-'.$color['id'],
                'name' => (string) $color['name'],
                'available' => $available,
            ];
        }

        foreach ($configuration['inserts'] ?? [] as $insert) {
            $available = Insert::query()
                ->whereKey($insert['id'] ?? null)
                ->where('is_available', true)
                ->exists();
            $materials[] = [
                'key' => 'insert-'.($insert['id'] ?? ''),
                'name' => (string) ($insert['name'] ?? 'Insert'),
                'available' => $available,
            ];
        }

        return $materials;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function transitionOrder(OrderItem $item, string $to, User $actor, array $options): OrderItem
    {
        return DB::transaction(function () use ($item, $to, $actor, $options): OrderItem {
            $locked = OrderItem::query()->with('order')->lockForUpdate()->findOrFail($item->id);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $allowed = match ($order->status) {
                OrderStatus::ReadyForDelivery => [OrderStatus::OutForDelivery->value],
                OrderStatus::OutForDelivery => [OrderStatus::Delivered->value],
                default => [],
            };
            if (! in_array($to, $allowed, true)) {
                throw new CheckoutException('Illegal order delivery transition.', 422, [
                    'allowed_next_states' => $allowed,
                ]);
            }
            if (! in_array($actor->role, [UserRole::Admin->value, UserRole::Producer->value], true)) {
                throw new CheckoutException('Only producers and admins can update delivery.', 403);
            }
            if ($order->payment_status !== PaymentStatus::Paid) {
                throw new CheckoutException('The balance must be paid before delivery.', 422);
            }

            $from = $order->status->value;
            $order->status = OrderStatus::from($to);
            if ($to === OrderStatus::OutForDelivery->value) {
                $order->courier_name = $options['courier_name'] ?? $order->courier_name;
                $order->courier_phone = $options['courier_phone'] ?? $order->courier_phone;
                $order->courier_reference = $options['courier_reference'] ?? $order->courier_reference;
            } else {
                $order->delivered_at = now();
            }
            $order->save();
            $eventType = $to === OrderStatus::OutForDelivery->value
                ? ProductionEventType::OutForDelivery
                : ProductionEventType::Delivered;
            $this->record($locked, $from, $to, $actor, $options['note'] ?? null, $eventType);
            $orderId = $order->id;
            $notice = $to === OrderStatus::OutForDelivery->value ? 'out_for_delivery' : 'delivered';
            DB::afterCommit(static function () use ($orderId, $notice): void {
                $deliveryOrder = Order::query()->with('user')->findOrFail($orderId);
                Mail::to($deliveryOrder->user->email)
                    ->queue((new OrderNoticeMail($deliveryOrder, $notice))->afterCommit());
            });

            return $locked->fresh(['order']);
        });
    }

    private function record(
        OrderItem $item,
        string $from,
        string $to,
        ?User $actor,
        ?string $note,
        ProductionEventType $eventType = ProductionEventType::StatusChanged,
    ): ProductionEvent {
        return ProductionEvent::query()->create([
            'order_id' => $item->order_id,
            'order_item_id' => $item->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
