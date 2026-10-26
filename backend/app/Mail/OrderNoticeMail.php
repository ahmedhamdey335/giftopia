<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order, public readonly string $notice) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->notice) {
            'balance_received' => 'Balance received for your Giftopia order',
            'out_for_delivery' => 'Your Giftopia order is on its way',
            default => 'Your Giftopia order was delivered',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-notice');
    }
}
