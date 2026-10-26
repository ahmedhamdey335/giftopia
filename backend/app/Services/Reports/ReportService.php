<?php

namespace App\Services\Reports;

use App\Models\BaseItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StudioEvent;
use App\Models\Technique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        $orders = Order::query()->whereBetween('created_at', [$from, $to])->get();
        $orderIds = $orders->modelKeys();
        $proofs = DB::table('proofs')->join('order_items', 'proofs.order_item_id', '=', 'order_items.id')
            ->whereIn('order_items.order_id', $orderIds)
            ->whereBetween('proofs.requested_at', [$from, $to])
            ->get(['proofs.version', 'proofs.status', 'proofs.response_note']);
        $eligibleOnTime = $orders->filter(fn (Order $order): bool => in_array($order->status?->value, [
            'delivered',
            'ready_for_delivery',
        ], true));
        $deliveredOnTime = $eligibleOnTime->filter(fn (Order $order): bool => (
            $order->delivered_at !== null && $order->delivered_at->lessThanOrEqualTo($order->ready_by)
        ) || (
            $order->ready_for_delivery_at !== null
            && $order->ready_for_delivery_at->lessThanOrEqualTo($order->ready_by)
        ))->count();
        $refunds = DB::table('refund_requests')->whereIn('order_id', $orderIds)->get(['status']);
        $funnel = StudioEvent::query()->whereBetween('created_at', [$from, $to])
            ->select('step', DB::raw("SUM(CASE WHEN event = 'viewed' THEN 1 ELSE 0 END) as viewed"),
                DB::raw("SUM(CASE WHEN event = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw("SUM(CASE WHEN event = 'abandoned' THEN 1 ELSE 0 END) as abandoned"))
            ->groupBy('step')->get()->keyBy('step');

        $funnelSteps = [];
        for ($step = 1; $step <= 6; $step++) {
            $row = $funnel->get($step);
            $viewed = (int) ($row->viewed ?? 0);
            $completed = (int) ($row->completed ?? 0);
            $abandoned = (int) ($row->abandoned ?? 0);
            $funnelSteps[] = [
                'step' => $step,
                'viewed' => $viewed,
                'completed' => $completed,
                'abandoned' => $abandoned,
                'completion_rate_percent' => $this->percent($completed, $viewed),
                'abandonment_rate_percent' => $this->percent($abandoned, $viewed),
            ];
        }
        $readyOrders = $orders->filter(fn (Order $order): bool => $order->deposit_paid_at !== null
            && $order->ready_for_delivery_at !== null);
        $averageDays = $readyOrders->isEmpty() ? 0 : round($readyOrders->avg(
            fn (Order $order): float => $order->deposit_paid_at->diffInMinutes($order->ready_for_delivery_at) / 1440,
        ), 2);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'orders_created' => $orders->count(),
            'deposit_paid_rate_percent' => $this->percent($orders->whereNotNull('deposit_paid_at')->count(), $orders->count()),
            'proofs_approved_first_version_percent' => $this->percent(
                $proofs->where('version', 1)->where('status', 'approved')->count(),
                $proofs->where('version', 1)->count(),
            ),
            'proofs_auto_approved_percent' => $this->percent(
                $proofs->where('status', 'approved')->where('response_note', 'auto-approved')->count(),
                $proofs->count(),
            ),
            'on_time_rate_percent' => $this->percent($deliveredOnTime, $eligibleOnTime->count()),
            'refund_request_rate_percent' => $this->percent($refunds->count(), $orders->count()),
            'approved_refund_rate_percent' => $this->percent(
                $refunds->whereIn('status', ['approved', 'refunded'])->count(),
                $refunds->count(),
            ),
            'refund_target_percent' => 5,
            'average_days_from_deposit_to_ready_for_delivery' => $averageDays,
            'funnel' => $funnelSteps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function estimates(Carbon $from, Carbon $to): array
    {
        $items = OrderItem::query()->with('order')
            ->whereNotNull('actual_labor_minutes')
            ->whereHas('order', fn ($orders) => $orders->whereBetween('created_at', [$from, $to]))
            ->get();
        $minimumSamples = (int) config('studio.reporting.minimum_samples', 20);
        $threshold = (float) config('studio.reporting.variance_threshold_percent', 15);
        $baseItems = BaseItem::query()->pluck('name', 'id');
        $techniques = Technique::query()->pluck('name', 'id');

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'minimum_samples' => $minimumSamples,
            'variance_threshold_percent' => $threshold,
            'base_items' => $this->estimateGroups($items->groupBy(fn (OrderItem $item): int => (int) ($item->customization['base_item']['id'] ?? 0)), $baseItems),
            'techniques' => $this->estimateGroups($items->groupBy(fn (OrderItem $item): int => (int) ($item->customization['color']['technique']['id'] ?? 0)), $techniques),
        ];
    }

    private function estimateGroups($groups, $names): array
    {
        $minimumSamples = (int) config('studio.reporting.minimum_samples', 20);
        $threshold = (float) config('studio.reporting.variance_threshold_percent', 15);

        return $groups->map(function ($items, $id) use ($names, $minimumSamples, $threshold): array {
            $estimated = (float) $items->avg('labor_minutes_total');
            $actual = (float) $items->avg('actual_labor_minutes');
            $variance = $estimated === 0.0 ? 0.0 : round((($actual - $estimated) / $estimated) * 100, 2);

            return [
                'id' => (int) $id,
                'name' => $names->get($id),
                'sample_count' => $items->count(),
                'average_estimated_labor_minutes' => round($estimated, 2),
                'average_actual_labor_minutes' => round($actual, 2),
                'variance_percent' => $variance,
                'suggest_adjustment' => $items->count() >= $minimumSamples && abs($variance) > $threshold,
            ];
        })->values()->all();
    }

    private function percent(int $numerator, int $denominator): float
    {
        return $denominator === 0 ? 0.0 : round($numerator / $denominator * 100, 2);
    }
}
