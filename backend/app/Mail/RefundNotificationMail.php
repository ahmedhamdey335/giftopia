<?php

namespace App\Mail;

use App\Models\RefundRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RefundNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly RefundRequest $refund, public readonly bool $staff = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->staff ? 'Giftopia refund request needs review' : 'Giftopia refund request update');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.refund-notification');
    }
}
