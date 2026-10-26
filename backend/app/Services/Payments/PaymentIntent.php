<?php

namespace App\Services\Payments;

final readonly class PaymentIntent
{
    public function __construct(
        public string $reference,
        public ?string $redirectUrl,
        public int $amount,
    ) {}
}
