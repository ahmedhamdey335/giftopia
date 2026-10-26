<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Proof;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProofNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Proof $proof,
        public readonly string $notificationType,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->notificationType) {
            'requested' => 'Your Giftopia personalization proof is ready',
            'reminder' => 'Reminder: review your Giftopia proof',
            'auto_approved' => 'Your Giftopia proof was automatically approved',
            default => 'Your Giftopia proof was approved',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.proof-notification');
    }
}
