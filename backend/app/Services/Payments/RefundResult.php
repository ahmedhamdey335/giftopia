<?php

namespace App\Services\Payments;

final readonly class RefundResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $reference,
        public ?string $failureReason = null,
    ) {}
}
