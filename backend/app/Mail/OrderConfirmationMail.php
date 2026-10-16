<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Giftopia order confirmation '.$this->order->order_number,
        );
    }

    public function content(): Content
    {
        $items = $this->order->items->map(function ($item): string {
            $snapshot = $item->customization;

            return strip_tags((string) ($snapshot['base_item']['name'] ?? 'Custom handmade item'));
        })->all();
        $whole = intdiv($this->order->total, 100);
        $fraction = $this->order->total % 100;

        return new Content(
            view: 'emails.order-confirmation',
            with: [
                'orderNumber' => strip_tags($this->order->order_number),
                'items' => $items,
                'total' => sprintf('%s %d.%02d', $this->order->currency, $whole, $fraction),
                'readyDate' => $this->order->ready_by
                    ->setTimezone((string) config('studio.timezone', 'Africa/Cairo'))
                    ->format('F j, Y'),
                'handmadeVariationNote' => 'Each piece is handmade, so slight variations in color and detail are part of its character.',
            ],
        );
    }
}
