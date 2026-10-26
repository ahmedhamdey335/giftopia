<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BalanceRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $qcPhotoUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Giftopia piece is ready for final payment');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.balance-requested',
            with: [
                'orderNumber' => strip_tags($this->order->order_number),
                'balanceAmount' => sprintf(
                    '%s %d.%02d',
                    $this->order->currency,
                    intdiv($this->order->balance_amount, 100),
                    $this->order->balance_amount % 100,
                ),
                'qcPhotoUrl' => $this->qcPhotoUrl,
                'frontendUrl' => (string) config('app.frontend_url', ''),
            ],
        );
    }
}
