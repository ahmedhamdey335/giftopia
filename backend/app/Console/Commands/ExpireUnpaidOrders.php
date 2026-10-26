<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentRecordStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'giftopia:expire-unpaid-orders';

    protected $description = 'Cancel awaiting-deposit orders whose deposit hold has expired.';

    public function handle(): int
    {
        $cancelled = 0;
        Order::query()
            ->where('status', OrderStatus::AwaitingDeposit->value)
            ->whereNotNull('deposit_expires_at')
            ->where('deposit_expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$cancelled): void {
                foreach ($orders as $candidate) {
                    $wasCancelled = DB::transaction(function () use ($candidate): bool {
                        $order = Order::query()->lockForUpdate()->findOrFail($candidate->id);
                        if ($order->status !== OrderStatus::AwaitingDeposit || $order->deposit_expires_at?->isFuture()) {
                            return false;
                        }

                        $order->status = OrderStatus::Cancelled;
                        $order->cancelled_at = now();
                        $order->save();
                        $order->payments()
                            ->where('kind', PaymentKind::Deposit->value)
                            ->where('status', PaymentRecordStatus::Pending->value)
                            ->update(['status' => PaymentRecordStatus::Cancelled->value]);

                        return true;
                    });
                    $cancelled += (int) $wasCancelled;
                }
            });

        $this->info("Cancelled {$cancelled} expired orders.");

        return self::SUCCESS;
    }
}
