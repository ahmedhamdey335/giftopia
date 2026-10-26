<?php

namespace App\Services\Production;

use App\Enums\ProductionEventType;
use App\Enums\ProductionStatus;
use App\Enums\ProofStatus;
use App\Exceptions\CheckoutException;
use App\Mail\ProofNotificationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Proof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class ProofService
{
    public function __construct(
        private readonly TransitionService $transitions,
        private readonly ScheduleService $schedule,
    ) {}

    public function createInitial(Order $order): void
    {
        foreach ($order->items()->where('has_text', true)->get() as $item) {
            $personalization = (array) ($item->customization['personalization'] ?? []);
            $proof = $item->proofs()->create([
                'version' => 1,
                'text' => (string) ($personalization['text'] ?? ''),
                'font_name' => (string) ($personalization['font']['name'] ?? ''),
                'text_color_name' => (string) ($personalization['text_color']['name'] ?? ''),
                'note' => $personalization['note'] ?? null,
                'status' => ProofStatus::Pending->value,
                'requested_at' => now(),
                'auto_approve_at' => now()->addHours((int) config('studio.delivery.proof_auto_approve_hours', 48)),
            ]);
            $this->transitions->recordEvent(
                $item,
                ProductionEventType::ProofRequested,
                'placed',
                'awaiting_proof',
                null,
            );
            $order->loadMissing('user');
            Mail::to($order->user->email)
                ->queue((new ProofNotificationMail($order, $proof, 'requested'))->afterCommit());
        }
        $this->schedule->planOrder($order);
    }

    public function approve(OrderItem $item, ?User $actor, bool $automatic = false): Proof
    {
        return DB::transaction(function () use ($item, $actor, $automatic): Proof {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $proof = $locked->proofs()->where('status', ProofStatus::Pending->value)->latest('version')->lockForUpdate()->first();
            if ($proof === null) {
                throw new CheckoutException('There is no pending proof to approve.', 422);
            }
            $proof->update([
                'status' => ProofStatus::Approved->value,
                'responded_at' => now(),
                'response_note' => $automatic ? 'auto-approved' : null,
            ]);
            $from = $locked->production_status->value;
            $locked->production_status = ProductionStatus::ReadyToStart;
            $locked->proof_status = ProofStatus::Approved;
            $locked->save();
            $this->transitions->recordEvent(
                $locked,
                ProductionEventType::ProofApproved,
                $from,
                ProductionStatus::ReadyToStart->value,
                $automatic ? 'auto-approved' : null,
                $actor,
            );
            $order = $locked->order()->with('user')->firstOrFail();
            Mail::to($order->user->email)
                ->queue((new ProofNotificationMail($order, $proof, $automatic ? 'auto_approved' : 'approved'))->afterCommit());

            return $proof;
        });
    }

    public function requestChanges(OrderItem $item, string $note, User $customer): Proof
    {
        return DB::transaction(function () use ($item, $note, $customer): Proof {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $proof = $locked->proofs()->where('status', ProofStatus::Pending->value)->latest('version')->lockForUpdate()->first();
            if ($proof === null) {
                throw new CheckoutException('There is no pending proof to change.', 422);
            }
            $proof->update([
                'status' => ProofStatus::ChangesRequested->value,
                'responded_at' => now(),
                'response_note' => $note,
            ]);
            $locked->proof_status = ProofStatus::ChangesRequested;
            $locked->save();
            $this->transitions->recordEvent(
                $locked,
                ProductionEventType::ProofChangesRequested,
                ProductionStatus::AwaitingProof->value,
                ProductionStatus::AwaitingProof->value,
                $note,
                $customer,
            );

            return $proof;
        });
    }

    public function revise(OrderItem $item, string $text, ?string $note, User $actor): Proof
    {
        $item->loadMissing('order');
        $max = (int) ($item->customization['base_item']['max_text_chars'] ?? 0);
        if ($max > 0 && mb_strlen($text) > $max) {
            throw new CheckoutException('The revised text exceeds the item personalization limit.', 422, [
                'text' => ["Text must be {$max} characters or fewer."],
            ]);
        }

        return DB::transaction(function () use ($item, $text, $note, $actor): Proof {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $latest = $locked->proofs()->latest('version')->lockForUpdate()->firstOrFail();
            if (! in_array($latest->status, [ProofStatus::Pending->value, ProofStatus::ChangesRequested->value], true)) {
                throw new CheckoutException('Only an unanswered proof can be revised.', 422);
            }
            $latest->update(['status' => 'superseded']);
            $proof = $locked->proofs()->create([
                'version' => $latest->version + 1,
                'text' => $text,
                'font_name' => $latest->font_name,
                'text_color_name' => $latest->text_color_name,
                'note' => $note,
                'status' => ProofStatus::Pending->value,
                'requested_at' => now(),
                'auto_approve_at' => now()->addHours((int) config('studio.delivery.proof_auto_approve_hours', 48)),
            ]);
            $customization = $locked->customization;
            $customization['personalization']['text'] = $text;
            $customization['personalization']['note'] = $note;
            $locked->customization = $customization;
            $locked->proof_status = ProofStatus::Pending;
            $locked->save();
            $this->transitions->recordEvent(
                $locked,
                ProductionEventType::ProofRequested,
                ProductionStatus::AwaitingProof->value,
                ProductionStatus::AwaitingProof->value,
                'Proof revised to version '.$proof->version.'.',
                $actor,
            );
            $order = $locked->order()->with('user')->firstOrFail();
            Mail::to($order->user->email)
                ->queue((new ProofNotificationMail($order, $proof, 'requested'))->afterCommit());

            return $proof;
        });
    }

    public function processDue(): int
    {
        $processed = 0;
        Proof::query()->where('status', ProofStatus::Pending->value)
            ->where('auto_approve_at', '<=', now())
            ->with('item')
            ->chunkById(100, function ($proofs) use (&$processed): void {
                foreach ($proofs as $proof) {
                    $this->approve($proof->item, null, true);
                    $processed++;
                }
            });

        return $processed;
    }

    public function sendReminders(): int
    {
        $proofs = Proof::query()->where('status', ProofStatus::Pending->value)
            ->whereNull('reminder_sent_at')
            ->where('requested_at', '<=', now()->subHours(24))
            ->with('item.order.user')->get();
        foreach ($proofs as $proof) {
            $proof->update(['reminder_sent_at' => now()]);
            Mail::to($proof->item->order->user->email)
                ->queue((new ProofNotificationMail($proof->item->order, $proof, 'reminder'))->afterCommit());
        }

        return $proofs->count();
    }
}
