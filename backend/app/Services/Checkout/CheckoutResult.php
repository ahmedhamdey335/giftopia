<?php

namespace App\Services\Checkout;

use App\Models\Order;
use App\Models\Quote;

final readonly class CheckoutResult
{
    private function __construct(
        public ?Order $order,
        public ?Quote $updatedQuote,
        public int $status,
    ) {}

    public static function order(Order $order, bool $existing = false): self
    {
        return new self($order, null, $existing ? 200 : 201);
    }

    public static function quote(Quote $quote): self
    {
        return new self(null, $quote, 409);
    }

    public function isPriceChanged(): bool
    {
        return $this->updatedQuote !== null;
    }
}
