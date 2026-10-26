<?php

namespace App\Services\Payments;

use App\Enums\PaymentRecordStatus;

final readonly class PaymentEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $reference,
        public PaymentRecordStatus $status,
        public int $amount,
        public array $raw,
    ) {}
}
