<?php

namespace App\Services\Production;

use App\Enums\ProofStatus;
use App\Exceptions\CheckoutException;
use App\Models\Asset;
use App\Models\OrderItem;
use Illuminate\Support\Facades\URL;

final class ProductionSheetService
{
    public function __construct(
        private readonly TransitionService $transitions,
        private readonly AttentionService $attention,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function card(OrderItem $item): array
    {
        $item->loadMissing(['order.items']);
        $snapshot = $item->customization;
        $checks = (array) ($item->checklist ?? []);
        $technique = $snapshot['color']['technique'] ?? null;
        $inserts = (array) ($snapshot['inserts'] ?? []);
        $steps = [
            ['key' => 'mix', 'label' => 'Mix'],
            ['key' => 'pour_base', 'label' => 'Pour base'],
        ];
        if (mb_strtolower((string) ($technique['name'] ?? '')) !== 'solid') {
            $steps[] = ['key' => 'blend', 'label' => 'Blend'];
        }
        if ($inserts !== []) {
            $steps[] = ['key' => 'add_inserts', 'label' => 'Add inserts'];
        }
        $steps = [
            ...$steps,
            ['key' => 'cure', 'label' => 'Cure'],
            ['key' => 'finish', 'label' => 'Finish'],
            ['key' => 'qc_photo', 'label' => 'QC photo'],
        ];
        $steps = array_map(
            static fn (array $step): array => [
                ...$step,
                'done' => (bool) ($checks['steps'][$step['key']] ?? false),
            ],
            $steps,
        );

        $materials = array_map(
            static fn (array $material): array => [
                ...$material,
                'done' => (bool) ($checks['materials'][$material['key']] ?? false),
            ],
            $this->transitions->materials($item),
        );
        $reasons = $this->attention->reasons($item);
        $itemIds = $item->order->items->modelKeys();
        $position = array_search($item->id, $itemIds, true);
        $references = Asset::query()
            ->where('order_item_id', $item->id)
            ->whereIn('kind', ['inspiration', 'proof'])
            ->get()
            ->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'kind' => $asset->kind,
                'url' => URL::temporarySignedRoute('assets.file', now()->addMinutes(15), ['asset' => $asset->id]),
            ])
            ->values()
            ->all();
        $personalization = (array) ($snapshot['personalization'] ?? []);
        $text = $item->proof_status === ProofStatus::Approved
            ? ($personalization['text'] ?? null)
            : null;

        return [
            'item_id' => $item->id,
            'order_number' => $item->order->order_number,
            'item_position' => ['number' => ($position === false ? 0 : $position) + 1, 'total' => count($itemIds)],
            'production_status' => $item->production_status->value,
            'due_date' => $item->order->ready_by->toIso8601String(),
            'flags' => [
                'personalization' => $item->has_text,
                'gift' => $item->order->gift_wrap || filled($item->order->gift_message),
                'long_cure' => (int) ($snapshot['cure_hours'] ?? 0) > 24,
                'overdue' => $this->hasReason($reasons, 'overdue'),
                'at_risk' => $this->hasReason($reasons, 'at_risk'),
            ],
            'look' => $snapshot['mood']['name'] ?? null,
            'colors' => $snapshot['color'] ?? [],
            'technique' => $technique,
            'inserts' => $inserts,
            'text' => $text === null ? null : [
                'value' => strip_tags((string) $text),
                'proof_version' => null,
            ],
            'customer_note' => isset($personalization['note']) ? strip_tags((string) $personalization['note']) : null,
            'references' => $references,
            'materials_check' => $materials,
            'steps' => $steps,
            'gift_message' => $item->order->gift_message === null ? null : strip_tags($item->order->gift_message),
            'gift_wrap' => $item->order->gift_wrap,
            'delivery' => [
                'recipient_name' => strip_tags($item->order->shipping_name),
                'phone' => strip_tags($item->order->shipping_phone),
                'address_line1' => strip_tags($item->order->shipping_address_line1),
                'address_line2' => $item->order->shipping_address_line2 === null ? null : strip_tags($item->order->shipping_address_line2),
                'city' => strip_tags($item->order->shipping_city),
                'governorate' => strip_tags($item->order->shipping_governorate),
                'courier_name' => $item->order->courier_name === null ? null : strip_tags($item->order->courier_name),
                'courier_phone' => $item->order->courier_phone === null ? null : strip_tags($item->order->courier_phone),
                'courier_reference' => $item->order->courier_reference === null ? null : strip_tags($item->order->courier_reference),
            ],
            'allowed_next_transitions' => $this->transitions->allowedNextTransitions($item),
            'attention_reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $checks
     */
    public function updateChecklist(OrderItem $item, array $checks): OrderItem
    {
        $card = $this->card($item);
        $allowedSteps = array_column($card['steps'], 'key');
        $allowedMaterials = array_column($card['materials_check'], 'key');
        $stepChecks = (array) ($checks['steps'] ?? []);
        $materialChecks = (array) ($checks['materials'] ?? []);
        $invalidSteps = array_diff(array_keys($stepChecks), $allowedSteps);
        $invalidMaterials = array_diff(array_keys($materialChecks), $allowedMaterials);
        if ($invalidSteps !== [] || $invalidMaterials !== []) {
            throw new CheckoutException('Checklist contains an unknown step or material.', 422, [
                'checks' => ['Refresh the production card and retry.'],
            ]);
        }

        $saved = (array) ($item->checklist ?? []);
        $item->checklist = [
            'steps' => array_merge((array) ($saved['steps'] ?? []), $stepChecks),
            'materials' => array_merge((array) ($saved['materials'] ?? []), $materialChecks),
        ];
        $item->save();

        return $item->fresh();
    }

    /**
     * @param  list<array{code: string, note: ?string}>  $reasons
     */
    private function hasReason(array $reasons, string $code): bool
    {
        return collect($reasons)->contains('code', $code);
    }
}
